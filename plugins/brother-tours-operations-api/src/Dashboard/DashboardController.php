<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Dashboard;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;

use function BrotherTours\OperationsApi\response;
use function BrotherTours\OperationsApi\table_exists;

final class DashboardController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => Csrf::authorize( $request, 'edit_posts', false ),
			)
		);
	}

	public function get( WP_REST_Request $request ) {
		global $wpdb;
		$from = $this->date_param( $request->get_param( 'from' ) ) ?: gmdate( 'Y-m-d', strtotime( '-29 days' ) );
		$to   = $this->date_param( $request->get_param( 'to' ) ) ?: gmdate( 'Y-m-d' );

		$bookings_table    = $wpdb->prefix . 'wpistic_bookings';
		$transactions      = $wpdb->prefix . 'wpistic_transactions';
		$connection_log    = $wpdb->prefix . 'wpistic_connection_log';
		$booking_available = table_exists( $bookings_table );

		$booking_stats = array(
			'today'          => 0,
			'newWorkflow'    => 0,
			'openLifecycle'  => 0,
			'confirmed'      => 0,
			'unassigned'     => 0,
			'periodTotal'    => 0,
		);
		$recent = array();
		$series = array();

		if ( $booking_available ) {
			$today_start = gmdate( 'Y-m-d 00:00:00' );
			$today_end   = gmdate( 'Y-m-d 23:59:59' );
			$booking_stats['today'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE created_at BETWEEN %s AND %s", $today_start, $today_end ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			$booking_stats['newWorkflow']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE portal_status='new'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$booking_stats['openLifecycle'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status NOT IN ('completed','expired','refunded','cancelled')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$booking_stats['confirmed']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status IN ('confirmed','balance_due','paid_in_full','completed')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$booking_stats['unassigned']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE (assigned_to IS NULL OR assigned_to=0) AND portal_status != 'closed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$booking_stats['periodTotal']   = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE created_at BETWEEN %s AND %s", $from . ' 00:00:00', $to . ' 23:59:59' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			$recent_rows = $wpdb->get_results( "SELECT id,reference,type,status,portal_status,customer_name,customer_email,tour_id,assigned_to,created_at FROM {$bookings_table} ORDER BY id DESC LIMIT 8", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $recent_rows as $row ) {
				$recent[] = $this->booking_summary( $row );
			}

			$daily = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) day, COUNT(*) total FROM {$bookings_table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				),
				ARRAY_A
			);
			$map = array();
			foreach ( (array) $daily as $row ) {
				$map[ (string) $row['day'] ] = (int) $row['total'];
			}
			$cursor = new \DateTimeImmutable( $from, new \DateTimeZone( 'UTC' ) );
			$end    = new \DateTimeImmutable( $to, new \DateTimeZone( 'UTC' ) );
			while ( $cursor <= $end && count( $series ) < 93 ) {
				$key      = $cursor->format( 'Y-m-d' );
				$series[] = array( 'date' => $key, 'inquiries' => $map[ $key ] ?? 0 );
				$cursor   = $cursor->modify( '+1 day' );
			}
		}

		$revenue = array();
		if ( table_exists( $transactions ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT currency, SUM(CASE WHEN status='paid' THEN CAST(amount AS DECIMAL(18,2)) ELSE 0 END) total FROM {$transactions} WHERE created_at BETWEEN %s AND %s GROUP BY currency", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$from . ' 00:00:00',
					$to . ' 23:59:59'
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				$revenue[] = array( 'currency' => (string) $row['currency'], 'amount' => (string) $row['total'] );
			}
		}

		$formistic = array(
			'available'        => class_exists( '\\Wpistic_Formistic_Database' ),
			'today'            => 0,
			'overdue24h'       => 0,
			'avgReplySeconds'  => null,
			'repliedRate'      => null,
			'statusCounts'     => array(),
			'subscribers'      => array(),
		);
		if ( $formistic['available'] ) {
			$formistic['today']           = (int) \Wpistic_Formistic_Database::today_count();
			$formistic['overdue24h']      = (int) \Wpistic_Formistic_Database::overdue_submissions_count( 24 );
			$formistic['avgReplySeconds'] = (int) \Wpistic_Formistic_Database::avg_reply_time_seconds();
			$formistic['repliedRate']     = (float) \Wpistic_Formistic_Database::replied_rate();
			$formistic['statusCounts']    = \Wpistic_Formistic_Database::status_counts();
			if ( class_exists( '\\Wpistic_Formistic_Newsletter' ) ) {
				$formistic['subscribers'] = \Wpistic_Formistic_Newsletter::subscriber_counts();
			}
		}

		$departures = get_posts(
			array(
				'post_type'      => 'wpistic_departure',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => 'wpistic_dep_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		$upcoming = 0;
		$limited  = 0;
		$today    = gmdate( 'Y-m-d' );
		foreach ( $departures as $departure_id ) {
			$date = (string) get_post_meta( (int) $departure_id, 'wpistic_dep_date', true );
			if ( $date >= $today ) {
				++$upcoming;
				if ( 'limited' === get_post_meta( (int) $departure_id, 'wpistic_dep_status', true ) ) {
					++$limited;
				}
			}
		}

		$connection_failures = 0;
		if ( table_exists( $connection_log ) ) {
			$connection_failures = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$connection_log} WHERE created_at >= %s AND (status_code < 200 OR status_code >= 300)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
				)
			);
		}

		return response(
			array(
				'range'       => array( 'from' => $from, 'to' => $to ),
				'bookings'    => array_merge( array( 'available' => $booking_available ), $booking_stats ),
				'revenue'     => $revenue,
				'forms'       => $formistic,
				'content'     => array(
					'toursPublished'        => (int) ( wp_count_posts( 'wpistic_tour' )->publish ?? 0 ),
					'destinationsPublished' => (int) ( wp_count_posts( 'wpistic_destination' )->publish ?? 0 ),
					'experiencesPublished'  => (int) ( wp_count_posts( 'wpistic_experience' )->publish ?? 0 ),
				),
				'departures'   => array( 'upcoming' => $upcoming, 'limited' => $limited ),
				'integrations' => array( 'connectionFailures24h' => $connection_failures ),
				'recent'       => $recent,
				'series'       => $series,
			)
		);
	}

	/** @param array<string,mixed> $row */
	private function booking_summary( array $row ): array {
		return array(
			'id'           => (int) $row['id'],
			'reference'    => (string) $row['reference'],
			'type'         => (string) $row['type'],
			'status'       => (string) $row['status'],
			'workflow'     => (string) $row['portal_status'],
			'customerName' => (string) $row['customer_name'],
			'customerEmail'=> (string) $row['customer_email'],
			'tourId'       => (int) $row['tour_id'],
			'assignedTo'   => (int) $row['assigned_to'],
			'createdAt'    => (string) $row['created_at'],
		);
	}

	private function date_param( mixed $value ): string {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
}
