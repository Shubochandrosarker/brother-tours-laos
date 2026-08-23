<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Itineraries;

use BrotherTours\OperationsApi\Auth\Csrf;
use BrotherTours\OperationsApi\Payments\PaymentsController;
use WP_REST_Request;
use wpdb;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

/**
 * Custom itineraries — private, per-guest itinerary documents linked to a
 * booking/enquiry. This is the backend of the dashboard's Itinerary Builder:
 * build day by day, price it, share a private review link with the guest,
 * refine, confirm.
 *
 * Storage is a dedicated table (not a CPT): itineraries are private
 * operational documents and must never be publicly queryable through WP_Query.
 * The guest share link is an opaque token URL rendered by template_redirect,
 * never a REST route, so unauthenticated API surfaces stay exactly as closed
 * as before.
 */
final class ItinerariesController {

	private const DB_VERSION = '1.2.0';

	private const STATUSES = array( 'draft', 'shared', 'confirmed' );

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'init', array( $this, 'maybe_install_table' ) );
		add_action( 'template_redirect', array( $this, 'guest_view' ) );
	}

	public function maybe_install_table(): void {
		if ( get_option( 'btoa_itineraries_db_version' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NULL DEFAULT NULL,
			booking_reference VARCHAR(60) NOT NULL DEFAULT '',
			guest_name VARCHAR(191) NOT NULL DEFAULT '',
			title VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			days LONGTEXT NULL,
			pricing LONGTEXT NULL,
			notes TEXT NULL,
			share_token VARCHAR(64) NULL DEFAULT NULL,
			created_by BIGINT UNSIGNED NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY share_token (share_token(16)),
			KEY booking_id (booking_id)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'btoa_itineraries_db_version', self::DB_VERSION );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/itineraries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/itineraries/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
				),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/itineraries/(?P<id>\d+)/share',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'share' ),
				'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ),
			)
		);
	}

	/* ------------------------------------------------------------------ */

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'btoa_itineraries';
	}

	public function list( WP_REST_Request $request ) {
		global $wpdb;
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$where  = array( '1=1' );
		$params = array();

		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		$booking_id = (int) $request->get_param( 'booking_id' );
		if ( $booking_id > 0 ) {
			$where[]  = 'booking_id = %d';
			$params[] = $booking_id;
		}
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(title LIKE %s OR guest_name LIKE %s OR booking_reference LIKE %s)';
			array_push( $params, $like, $like, $like );
		}

		$where_sql = implode( ' AND ', $where );
		$table     = self::table();
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) ); // phpcs:ignore
		$offset    = ( $page - 1 ) * $per_page;
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore

		return response( array(
			'items'      => array_map( array( $this, 'shape' ), $rows ?: array() ),
			'total'      => $total,
			'page'       => $page,
			'perPage'    => $per_page,
			'totalPages' => (int) ceil( $total / $per_page ),
		) );
	}

	public function create( WP_REST_Request $request ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			self::table(),
			array(
				'title'             => sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: 'Untitled itinerary' ) ),
				'guest_name'        => sanitize_text_field( (string) $request->get_param( 'guest_name' ) ),
				'booking_reference' => sanitize_text_field( (string) $request->get_param( 'booking_reference' ) ),
				'booking_id'        => ( (int) $request->get_param( 'booking_id' ) ) ?: null,
				'status'            => 'draft',
				'days'              => wp_json_encode( $this->sanitize_days( $request->get_param( 'days' ) ) ),
				'pricing'           => wp_json_encode( $this->sanitize_pricing( $request->get_param( 'pricing' ) ) ),
				'notes'             => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
				'created_by'        => get_current_user_id(),
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
		if ( ! $ok ) {
			return error( 'bt_ops_itinerary_create_failed', __( 'The itinerary could not be created.', 'brother-tours-operations-api' ), 500 );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $wpdb->insert_id ) ); // phpcs:ignore
		return response( $this->shape( $row ), 201 );
	}

	public function get( WP_REST_Request $request ) {
		$row = $this->fetch( (int) $request['id'] );
		if ( null === $row ) {
			return error( 'bt_ops_itinerary_not_found', __( 'Itinerary not found.', 'brother-tours-operations-api' ), 404 );
		}
		return response( $this->shape( $row ) );
	}

	public function update( WP_REST_Request $request ) {
		global $wpdb;
		$row = $this->fetch( (int) $request['id'] );
		if ( null === $row ) {
			return error( 'bt_ops_itinerary_not_found', __( 'Itinerary not found.', 'brother-tours-operations-api' ), 404 );
		}

		$data   = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$data['title'] = sanitize_text_field( (string) $title );
		}
		foreach ( array( 'guest_name' => 'guestName', 'booking_reference' => 'bookingReference', 'notes' => 'notes' ) as $column => $param ) {
			$value = $request->get_param( $column );
			if ( null === $value ) {
				$value = $request->get_param( $param );
			}
			if ( null !== $value ) {
				$data[ $column ] = 'notes' === $column ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
			}
		}
		$status = $request->get_param( 'status' );
		if ( null !== $status && in_array( sanitize_key( (string) $status ), self::STATUSES, true ) ) {
			$data['status'] = sanitize_key( (string) $status );
		}
		$days = $request->get_param( 'days' );
		if ( null !== $days ) {
			$data['days'] = wp_json_encode( $this->sanitize_days( $days ) );
		}
		$pricing = $request->get_param( 'pricing' );
		if ( null !== $pricing ) {
			$data['pricing'] = wp_json_encode( $this->sanitize_pricing( $pricing ) );
		}

		$wpdb->update( self::table(), $data, array( 'id' => (int) $row->id ) ); // phpcs:ignore
		return response( $this->shape( $this->fetch( (int) $row->id ) ) );
	}

	public function delete( WP_REST_Request $request ) {
		global $wpdb;
		$row = $this->fetch( (int) $request['id'] );
		if ( null === $row ) {
			return error( 'bt_ops_itinerary_not_found', __( 'Itinerary not found.', 'brother-tours-operations-api' ), 404 );
		}
		$wpdb->delete( self::table(), array( 'id' => (int) $row->id ) ); // phpcs:ignore
		return response( array( 'deleted' => true, 'id' => (int) $row->id ) );
	}

	/**
	 * Generates (or rotates) the private guest link and marks the itinerary
	 * shared. The token is 32 hex chars of randomness; the previous link dies
	 * with the rotation.
	 */
	public function share( WP_REST_Request $request ) {
		global $wpdb;
		$row = $this->fetch( (int) $request['id'] );
		if ( null === $row ) {
			return error( 'bt_ops_itinerary_not_found', __( 'Itinerary not found.', 'brother-tours-operations-api' ), 404 );
		}
		$token = bin2hex( random_bytes( 16 ) );
		$wpdb->update(
			self::table(),
			array(
				'share_token' => $token,
				'status'      => 'confirmed' === $row->status ? 'confirmed' : 'shared',
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id )
		); // phpcs:ignore
		return response( array(
			'shareUrl' => home_url( '/itinerary/' . $token . '/' ),
			'status'   => 'confirmed' === $row->status ? 'confirmed' : 'shared',
		) );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Public, read-only guest view at /itinerary/{token}/. Renders only
	 * guest-safe fields — internal notes never leave the building. Outputs a
	 * self-contained branded page with no theme dependencies.
	 */
	public function guest_view(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! preg_match( '#^/itinerary/([a-f0-9]{32})/?$#', $request_uri, $matches ) ) {
			return;
		}
		$token = $matches[1];
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, guest_name, status, days, pricing, updated_at FROM ' . self::table() . ' WHERE share_token = %s', $token ) ); // phpcs:ignore
		if ( null === $row ) {
			status_header( 404 );
			nocache_headers();
			exit( '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Not found — Brother Tours</title></head><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;background:#f7f5f0;color:#1c2b2f"><div style="text-align:center"><h1>This link is not available</h1><p style="color:#5c6b6f">Please ask Brother Tours for a fresh link.</p></div></body></html>' );
		}

		$days    = json_decode( (string) $row->days, true );
		$pricing = json_decode( (string) $row->pricing, true );
		if ( ! is_array( $days ) ) {
			$days = array();
		}
		if ( ! is_array( $pricing ) ) {
			$pricing = array();
		}
		$total    = ( is_numeric( $pricing['perPerson'] ?? null ) && is_numeric( $pricing['groupSize'] ?? null ) ) ? (float) $pricing['perPerson'] * (float) $pricing['groupSize'] : null;
		$currency = (string) ( $pricing['currency'] ?? 'USD' );
		$deposit  = null === $total ? null : round( $total * ( (float) ( $pricing['depositPercent'] ?? 30 ) / 100 ) );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		$money = static function ( $value ) use ( $currency ) {
			return null === $value ? '—' : htmlspecialchars( $currency . ' ' . number_format( $value ), ENT_QUOTES, 'UTF-8' );
		};
		$esc = static fn( string $value ) => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $esc( (string) $row->title ) . ' — Brother Tours</title><style>';
		echo ':root{--ink:#1c2b2f;--muted:#5c6b6f;--gold:#b3924f;--line:#e4dfd4;--bg:#f7f5f0;--card:#fff}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--ink);line-height:1.6}main{max-width:46rem;margin:0 auto;padding:2.5rem 1.25rem 4rem}h1{font-size:1.7rem;margin:.2rem 0 .4rem}p.sub{color:var(--muted);margin-top:0}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:1.2rem 1.4rem;margin:1rem 0;box-shadow:0 4px 14px rgba(28,43,47,.05)}.day-num{display:inline-flex;width:30px;height:30px;border-radius:50%;background:rgba(179,146,79,.14);color:var(--gold);font-weight:700;align-items:center;justify-content:center;font-size:.85rem;margin-right:.6rem}h2.day{display:flex;align-items:center;font-size:1.05rem;margin:0 0 .5rem}.meta{display:flex;flex-wrap:wrap;gap:.8rem;color:var(--muted);font-size:.8rem;margin-top:.6rem}.meta span{border:1px solid var(--line);border-radius:999px;padding:.1rem .6rem}.totals{display:flex;justify-content:space-between;padding:.45rem 0;border-top:1px solid var(--line)}.totals:first-of-type{border-top:0}.totals.grand{font-weight:700;color:var(--gold)}footer{margin-top:2.5rem;text-align:center;color:var(--muted);font-size:.8rem}.badge{display:inline-block;background:rgba(179,146,79,.14);color:var(--gold);border-radius:999px;padding:.15rem .7rem;font-size:.75rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}';
		echo '</style></head><body><main>';
		echo '<span class="badge">' . $esc( (string) $row->status ) . '</span>';
		echo '<h1>' . $esc( (string) $row->title ) . '</h1>';
		echo '<p class="sub">Prepared' . ( '' !== (string) $row->guest_name ? ' for ' . $esc( (string) $row->guest_name ) : '' ) . ' by Brother Tours · Updated ' . $esc( (string) $row->updated_at ) . '</p>';
		foreach ( $days as $index => $day ) {
			$d_title    = (string) ( $day['title'] ?? '' );
			$narrative  = (string) ( $day['narrative'] ?? '' );
			$overnight  = (string) ( $day['overnight'] ?? '' );
			$transfer   = (string) ( $day['transfer'] ?? '' );
			$meals      = is_array( $day['meals'] ?? null ) ? $day['meals'] : array();
			$meal_badge = array();
			foreach ( array( 'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner' ) as $key => $label ) {
				if ( ! empty( $meals[ $key ] ) ) {
					$meal_badge[] = $label;
				}
			}
			echo '<section class="card"><h2 class="day"><span class="day-num">' . (int) ( $index + 1 ) . '</span>' . $esc( $d_title ) . '</h2>';
			if ( '' !== $narrative ) {
				echo '<p style="white-space:pre-line">' . $esc( $narrative ) . '</p>';
			}
			echo '<div class="meta">';
			if ( '' !== $overnight ) {
				echo '<span>Overnight · ' . $esc( $overnight ) . '</span>';
			}
			if ( '' !== $transfer ) {
				echo '<span>Transfer · ' . $esc( $transfer ) . '</span>';
			}
			if ( $meal_badge ) {
				echo '<span>' . $esc( implode( ' · ', $meal_badge ) ) . '</span>';
			}
			echo '</div></section>';
		}
		if ( null !== $total ) {
			$balance = $total - (float) $deposit;
			echo '<section class="card"><h2 class="day">Pricing</h2>';
			echo '<div class="totals"><span>Total (' . (int) ( $pricing['groupSize'] ?? 0 ) . ' travellers)</span><span>' . $money( $total ) . '</span></div>';
			echo '<div class="totals"><span>Deposit to confirm</span><span>' . $money( $deposit ) . '</span></div>';
			echo '<div class="totals"><span>Balance</span><span>' . $money( $balance ) . '</span></div>';
			echo '</section>';

			PaymentsController::render_guest_section( $token, (int) $row->id, $total, $deposit, $currency );
		}
		echo '<footer>Brother Tours Sole Co., Ltd. · Vientiane, Laos · <a href="https://www.brothertours.com/" style="color:var(--gold)">brothertours.com</a><br>Questions about this itinerary? Reply to the email that shared this link.</footer>';
		echo '</main></body></html>';
		exit;
	}

	/* ------------------------------------------------------------------ */

	private function fetch( int $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	private function shape( $row ): array {
		if ( ! is_object( $row ) ) {
			return array();
		}
		$days    = json_decode( (string) ( $row->days ?? '' ), true );
		$pricing = json_decode( (string) ( $row->pricing ?? '' ), true );
		return array(
			'id'               => (int) $row->id,
			'title'            => (string) $row->title,
			'guestName'        => (string) $row->guest_name,
			'bookingReference' => (string) $row->booking_reference,
			'bookingId'        => null === $row->booking_id ? null : (int) $row->booking_id,
			'status'           => (string) $row->status,
			'days'             => is_array( $days ) ? $days : array(),
			'pricing'          => is_array( $pricing ) ? $pricing : array(),
			'notes'            => (string) ( $row->notes ?? '' ),
			'shareUrl'         => ! empty( $row->share_token ) ? home_url( '/itinerary/' . $row->share_token . '/' ) : '',
			'createdAt'        => (string) $row->created_at,
			'updatedAt'        => (string) $row->updated_at,
		);
	}

	/**
	 * Days arrive from the app as an array of day objects. Everything is
	 * normalized to the known keys so nothing unexpected is ever stored.
	 */
	private function sanitize_days( $days ): array {
		if ( ! is_array( $days ) ) {
			return array();
		}
		$clean = array();
		foreach ( $days as $day ) {
			if ( ! is_array( $day ) ) {
				continue;
			}
			$meals = is_array( $day['meals'] ?? null ) ? $day['meals'] : array();
			$clean[] = array(
				'title'     => sanitize_text_field( (string) ( $day['title'] ?? '' ) ),
				'narrative' => sanitize_textarea_field( (string) ( $day['narrative'] ?? '' ) ),
				'overnight' => sanitize_text_field( (string) ( $day['overnight'] ?? '' ) ),
				'transfer'  => sanitize_text_field( (string) ( $day['transfer'] ?? '' ) ),
				'meals'     => array(
					'breakfast' => ! empty( $meals['breakfast'] ),
					'lunch'     => ! empty( $meals['lunch'] ),
					'dinner'    => ! empty( $meals['dinner'] ),
				),
			);
		}
		return $clean;
	}

	private function sanitize_pricing( $pricing ): array {
		if ( ! is_array( $pricing ) ) {
			return array();
		}
		$currency = strtoupper( sanitize_key( (string) ( $pricing['currency'] ?? 'USD' ) ) );
		if ( ! in_array( $currency, array( 'USD', 'EUR', 'THB', 'LAK' ), true ) ) {
			$currency = 'USD';
		}
		$percent = (float) ( $pricing['depositPercent'] ?? 30 );
		return array(
			'currency'       => $currency,
			'perPerson'      => isset( $pricing['perPerson'] ) && is_numeric( $pricing['perPerson'] ) ? (float) $pricing['perPerson'] : null,
			'groupSize'      => isset( $pricing['groupSize'] ) && is_numeric( $pricing['groupSize'] ) ? (int) $pricing['groupSize'] : null,
			'depositPercent' => max( 0, min( 100, $percent ) ),
		);
	}
}
