<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

use Wpistic\TourManager\Booking\BookingService;
use Wpistic\TourManager\Connections\ConnectionsManager;
use Wpistic\TourManager\Payments\GatewayManager;

/**
 * Single-booking detail screen: Overview / Traveler / Trip / Payments /
 * Activity / Connections tabs, plus every state-changing action the old
 * Admin\Portal offered (workflow status, assignment, notes, lifecycle
 * transitions, deposit/balance link generation) reorganized under them.
 *
 * Owned as a collaborator by Bookings (see Bookings::register(), which
 * calls $this->detail->register()) rather than wired separately in
 * Plugin.php, since it only ever renders inside the bookings list screen's
 * ?view={id} state and has no menu entry of its own.
 */
final class BookingDetail {

	private const TABS = array( 'overview', 'traveler', 'trip', 'payments', 'activity', 'connections' );

	public function __construct(
		private BookingService $bookings,
		private GatewayManager $gateways,
		private ConnectionsManager $connections
	) {}

	public function register(): void {
		add_action( 'admin_post_wpistic_tm_portal', array( $this, 'action' ) );
		add_action( 'admin_post_wpistic_tm_resend_connection', array( $this, 'resend_connection' ) );
	}

	public function render( int $id ): void {
		$booking = $this->bookings->get( $id );
		if ( ! $booking ) {
			echo '<div class="wrap wpistic-tm-dashboard"' . wp_kses_post( AdminAssets::theme_attr() ) . '><p>' . esc_html__( 'Booking not found.', 'wpistic-tour-manager' ) . '</p></div>';
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, self::TABS, true ) ) {
			$tab = 'overview';
		}

		$link   = get_transient( 'wpistic_tm_link_' . $id );
		$notice = get_transient( 'wpistic_tm_notice_' . $id );
		delete_transient( 'wpistic_tm_link_' . $id );
		delete_transient( 'wpistic_tm_notice_' . $id );
		?>
		<div class="wrap wpistic-tm-dashboard"<?php echo wp_kses_post( AdminAssets::theme_attr() ); ?>>
			<div class="wpistic-tm-topbar">
				<h1>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings' ) ); ?>" class="wpistic-tm-back" aria-label="<?php esc_attr_e( 'Back to Bookings & Inquiries', 'wpistic-tour-manager' ); ?>">&larr;</a>
					<?php echo esc_html( (string) $booking['reference'] ); ?>
					<span class="wpistic-tm-badge"><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $booking['status'] ) ) ); ?></span>
				</h1>
				<?php AdminAssets::render_theme_toggle(); ?>
			</div>

			<?php if ( $notice ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( (string) $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( $link && is_array( $link ) ) : ?>
				<div class="notice notice-success"><p><strong><?php echo esc_html( (string) ( $link['label'] ?? __( 'Payment link', 'wpistic-tour-manager' ) ) ); ?>:</strong>
					<?php if ( ! empty( $link['url'] ) ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a>
					<?php else : ?>
						<br><pre><?php echo esc_html( (string) $link['instructions'] ); ?></pre>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<nav class="wpistic-tm-tabs" aria-label="<?php esc_attr_e( 'Booking detail sections', 'wpistic-tour-manager' ); ?>">
				<?php foreach ( self::TABS as $t ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . $id . '&tab=' . $t ) ); ?>" class="wpistic-tm-tab<?php echo $tab === $t ? ' is-active' : ''; ?>"<?php echo $tab === $t ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $this->tab_label( $t ) ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="wpistic-tm-detail-layout">
				<div class="wpistic-tm-detail-main">
					<?php
					switch ( $tab ) {
						case 'traveler':
							$this->render_traveler( $booking );
							break;
						case 'trip':
							$this->render_trip( $booking );
							break;
						case 'payments':
							$this->render_payments( $id, $booking );
							break;
						case 'activity':
							$this->render_activity( $id );
							break;
						case 'connections':
							$this->render_connections( $id );
							break;
						default:
							$this->render_overview( $booking );
					}
					?>
				</div>

				<div class="wpistic-tm-detail-aside">
					<?php $this->render_actions( $id, $booking ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function tab_label( string $tab ): string {
		$labels = array(
			'overview'    => __( 'Overview', 'wpistic-tour-manager' ),
			'traveler'    => __( 'Traveler', 'wpistic-tour-manager' ),
			'trip'        => __( 'Trip', 'wpistic-tour-manager' ),
			'payments'    => __( 'Payments', 'wpistic-tour-manager' ),
			'activity'    => __( 'Activity', 'wpistic-tour-manager' ),
			'connections' => __( 'Connections', 'wpistic-tour-manager' ),
		);
		return $labels[ $tab ] ?? ucfirst( $tab );
	}

	/**
	 * @param array<string, mixed> $booking
	 */
	private function render_overview( array $booking ): void {
		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'At a glance', 'wpistic-tour-manager' ) . '</h2>';
		$this->fact_table(
			array(
				__( 'Reference', 'wpistic-tour-manager' )   => (string) $booking['reference'],
				__( 'Type', 'wpistic-tour-manager' )        => ucwords( str_replace( '_', ' ', (string) $booking['type'] ) ),
				__( 'Lifecycle', 'wpistic-tour-manager' )   => ucwords( str_replace( '_', ' ', (string) $booking['status'] ) ),
				__( 'Workflow', 'wpistic-tour-manager' )    => ucfirst( (string) $booking['portal_status'] ),
				__( 'Assigned to', 'wpistic-tour-manager' ) => $booking['assigned_to'] ? (string) ( get_userdata( (int) $booking['assigned_to'] )->display_name ?? '' ) : __( '— None —', 'wpistic-tour-manager' ),
				__( 'Created', 'wpistic-tour-manager' )     => (string) $booking['created_at'],
				__( 'Source URL', 'wpistic-tour-manager' )  => (string) $booking['source_url'],
			)
		);
		echo '</section>';

		if ( '' !== trim( (string) ( $booking['special_requests'] ?? '' ) ) ) {
			echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Special requests', 'wpistic-tour-manager' ) . '</h2>';
			echo '<p class="wpistic-tm-notes-text">' . nl2br( esc_html( (string) $booking['special_requests'] ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nl2br() applied to an already-esc_html()'d string.
			echo '</section>';
		}
	}

	/**
	 * @param array<string, mixed> $booking
	 */
	private function render_traveler( array $booking ): void {
		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Traveler', 'wpistic-tour-manager' ) . '</h2>';
		$this->fact_table(
			array(
				__( 'Name', 'wpistic-tour-manager' )    => (string) $booking['customer_name'],
				__( 'Email', 'wpistic-tour-manager' )   => (string) $booking['customer_email'],
				__( 'Phone', 'wpistic-tour-manager' )   => (string) $booking['customer_phone'],
				__( 'Country', 'wpistic-tour-manager' ) => (string) $booking['customer_country'],
				__( 'Adults', 'wpistic-tour-manager' )  => (string) $booking['party_adults'],
				// Deliberately not cast to int for display either -- the
				// column is text and may read "2 (ages 5, 8)".
				__( 'Children', 'wpistic-tour-manager' )        => (string) $booking['party_children'],
				__( 'Hotel preference', 'wpistic-tour-manager' ) => (string) $booking['hotel_pref'],
			)
		);
		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $booking
	 */
	private function render_trip( array $booking ): void {
		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Trip', 'wpistic-tour-manager' ) . '</h2>';

		$tour_id = (int) ( $booking['tour_id'] ?? 0 );
		if ( $tour_id > 0 ) {
			$tour_title = get_the_title( $tour_id );
			$edit_link  = get_edit_post_link( $tour_id, '' );
			if ( '' !== $tour_title ) {
				echo '<p class="wpistic-tm-trip-tour"><strong>' . esc_html__( 'Tour', 'wpistic-tour-manager' ) . ':</strong> ';
				if ( $edit_link ) {
					echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $tour_title ) . '</a>';
				} else {
					echo esc_html( $tour_title );
				}
				echo '</p>';
			} else {
				/* translators: %d: tour post id. */
				echo '<p class="wpistic-tm-empty">' . esc_html( sprintf( __( 'Tour #%d no longer exists.', 'wpistic-tour-manager' ), $tour_id ) ) . '</p>';
			}
		} else {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No specific tour is associated with this request.', 'wpistic-tour-manager' ) . '</p>';
		}

		$departure_id = (int) ( $booking['departure_id'] ?? 0 );
		if ( $departure_id > 0 ) {
			$date = (string) get_post_meta( $departure_id, 'wpistic_dep_date', true );
			echo '<p><strong>' . esc_html__( 'Departure', 'wpistic-tour-manager' ) . ':</strong> ' . esc_html( $date ?: (string) $departure_id ) . '</p>';
		}

		$addons = json_decode( (string) ( $booking['addons'] ?? '' ), true );
		if ( is_array( $addons ) && $addons ) {
			echo '<p><strong>' . esc_html__( 'Add-ons', 'wpistic-tour-manager' ) . ':</strong> ' . esc_html( implode( ', ', array_map( 'strval', $addons ) ) ) . '</p>';
		}

		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $booking
	 */
	private function render_payments( int $id, array $booking ): void {
		global $wpdb;
		$txns = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpistic_transactions WHERE booking_id = %d ORDER BY id DESC", $id ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB

		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Pricing', 'wpistic-tour-manager' ) . '</h2>';
		$this->fact_table(
			array(
				__( 'Currency', 'wpistic-tour-manager' ) => (string) $booking['currency'],
				__( 'Price', 'wpistic-tour-manager' )    => (string) $booking['price_amount'],
				__( 'Deposit', 'wpistic-tour-manager' )  => (string) $booking['deposit_amount'],
				__( 'Balance', 'wpistic-tour-manager' )  => (string) $booking['balance_amount'],
			)
		);
		echo '</section>';

		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Transactions', 'wpistic-tour-manager' ) . '</h2>';
		if ( ! $txns ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'None yet.', 'wpistic-tour-manager' ) . '</p>';
		} else {
			echo '<table class="widefat striped wpistic-tm-table"><thead><tr><th>' . esc_html__( 'Gateway', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Type', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Amount', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Status', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Reference', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'When', 'wpistic-tour-manager' ) . '</th></tr></thead><tbody>';
			foreach ( $txns as $t ) {
				$status_ok = 'paid' === $t['status'];
				echo '<tr><td>' . esc_html( (string) $t['gateway'] ) . '</td><td>' . esc_html( (string) $t['type'] ) . '</td><td>' . esc_html( $t['amount'] . ' ' . $t['currency'] ) . '</td>';
				echo '<td><span class="wpistic-tm-badge ' . ( $status_ok ? 'wpistic-tm-badge--success' : '' ) . '">' . esc_html( (string) $t['status'] ) . '</span></td>';
				echo '<td><code>' . esc_html( (string) $t['gateway_txn_id'] ) . '</code></td><td>' . esc_html( (string) $t['created_at'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	private function render_activity( int $id ): void {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpistic_audit_log WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT 100", 'booking', $id ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB

		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Activity', 'wpistic-tour-manager' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No activity recorded yet.', 'wpistic-tour-manager' ) . '</p>';
		} else {
			echo '<ul class="wpistic-tm-activity">';
			foreach ( $rows as $row ) {
				$detail = json_decode( (string) $row['detail'], true );
				echo '<li><code>' . esc_html( (string) $row['created_at'] ) . '</code> — <strong>' . esc_html( (string) $row['actor'] ) . '</strong>: ' . esc_html( (string) $row['action'] );
				if ( is_array( $detail ) && $detail ) {
					echo ' <span class="wpistic-tm-list-meta">' . esc_html( wp_json_encode( $detail ) ?: '' ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	/**
	 * Connection (Tourflows/webhook) delivery history for this booking, read
	 * from the audit log via BookingService::connection_dispatch_log() --
	 * see that method and ConnectionsManager::send()'s
	 * `wpistic_tm_connection_dispatched` action for why this reads the audit
	 * log rather than wpistic_connection_log directly (that table has no
	 * booking_id column).
	 */
	private function render_connections( int $id ): void {
		$log = array_values(
			array_filter(
				$this->bookings->connection_dispatch_log( 500 ),
				static fn( $row ) => $id === (int) $row['object_id']
			)
		);

		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Delivery history', 'wpistic-tour-manager' ) . '</h2>';
		if ( ! $log ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No connection dispatches recorded for this booking yet.', 'wpistic-tour-manager' ) . '</p>';
		} else {
			echo '<table class="widefat striped wpistic-tm-table"><thead><tr><th>' . esc_html__( 'When', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Event', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Status', 'wpistic-tour-manager' ) . '</th></tr></thead><tbody>';
			foreach ( $log as $row ) {
				$detail = (array) $row['detail'];
				$code   = (int) ( $detail['status_code'] ?? 0 );
				$ok     = $code >= 200 && $code < 300;
				echo '<tr><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( (string) ( $detail['event'] ?? '' ) ) . '</td>';
				echo '<td><span class="wpistic-tm-badge ' . ( $ok ? 'wpistic-tm-badge--success' : 'wpistic-tm-badge--danger' ) . '">' . esc_html( (string) $code ) . '</span></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';

		echo '<section class="wpistic-tm-card"><h2>' . esc_html__( 'Manual resend', 'wpistic-tour-manager' ) . '</h2>';
		echo '<p class="wpistic-tm-card-note">' . esc_html__( 'Re-dispatch this booking to every enabled connection subscribed to inquiry.created. Use this after fixing a failed delivery -- e.g. once a Tourflows endpoint or secret has been corrected.', 'wpistic-tour-manager' ) . '</p>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpistic_tm_resend_connection">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'wpistic_tm_resend_connection_' . $id ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Resend to connections', 'wpistic-tour-manager' ); ?></button>
		</form>
		<?php
		echo '</section>';
	}

	/**
	 * @param array<string, string> $rows
	 */
	private function fact_table( array $rows ): void {
		$rows = array_filter( $rows, static fn( $v ) => '' !== trim( (string) $v ) );
		if ( ! $rows ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'Nothing on file.', 'wpistic-tour-manager' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped wpistic-tm-table wpistic-tm-facts"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th>' . esc_html( (string) $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The sticky action panel: workflow status, assignment, notes, lifecycle
	 * transitions, and deposit/balance link generation. Every field from the
	 * old Admin\Portal's single catch-all form, unchanged in behavior --
	 * only the layout (a persistent aside rather than a tab) changed.
	 *
	 * @param array<string, mixed> $booking
	 */
	private function render_actions( int $id, array $booking ): void {
		?>
		<div class="wpistic-tm-card wpistic-tm-sticky-actions">
			<h2><?php esc_html_e( 'Actions', 'wpistic-tour-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpistic_tm_portal">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'wpistic_tm_portal' ); ?>

				<p><label><strong><?php esc_html_e( 'Workflow', 'wpistic-tour-manager' ); ?></strong></label>
				<select name="portal_status">
					<?php foreach ( array( 'new', 'reviewed', 'sent', 'closed' ) as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $booking['portal_status'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
					<?php endforeach; ?>
				</select></p>

				<p><label><strong><?php esc_html_e( 'Assign to', 'wpistic-tour-manager' ); ?></strong></label>
				<?php
				wp_dropdown_users(
					array(
						'name'              => 'assigned_to',
						'selected'          => (int) ( $booking['assigned_to'] ?? 0 ),
						'show_option_none'  => __( '— None —', 'wpistic-tour-manager' ),
						'option_none_value' => 0,
					)
				);
				?></p>

				<p><label><strong><?php esc_html_e( 'Add note', 'wpistic-tour-manager' ); ?></strong></label>
				<textarea name="note" rows="3" class="widefat"></textarea></p>

				<p><label><strong><?php esc_html_e( 'Lifecycle action', 'wpistic-tour-manager' ); ?></strong></label>
				<select name="lifecycle_action">
					<option value=""><?php esc_html_e( 'No lifecycle change', 'wpistic-tour-manager' ); ?></option>
					<?php foreach ( $this->bookings->lifecycle_actions( $id ) as $action => $label ) : ?>
						<option value="<?php echo esc_attr( $action ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>

				<p><label><strong><?php esc_html_e( 'Generate deposit link', 'wpistic-tour-manager' ); ?></strong></label>
				<select name="gateway">
					<option value=""><?php esc_html_e( '— Select gateway —', 'wpistic-tour-manager' ); ?></option>
					<?php foreach ( $this->gateways->enabled() as $gateway ) : ?>
						<option value="<?php echo esc_attr( $gateway->id() ); ?>"><?php echo esc_html( $gateway->displayName() ); ?></option>
					<?php endforeach; ?>
				</select></p>

				<p><label><strong><?php esc_html_e( 'Generate balance link', 'wpistic-tour-manager' ); ?></strong></label>
				<select name="balance_gateway">
					<option value=""><?php esc_html_e( 'No balance link', 'wpistic-tour-manager' ); ?></option>
					<?php foreach ( $this->gateways->enabled() as $gateway ) : ?>
						<option value="<?php echo esc_attr( $gateway->id() ); ?>"><?php echo esc_html( $gateway->displayName() ); ?></option>
					<?php endforeach; ?>
				</select></p>

				<?php submit_button( __( 'Apply', 'wpistic-tour-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * State-changing action for the sticky panel form -- unchanged behavior
	 * from the old Admin\Portal::action(), same nonce/capability pattern.
	 */
	public function action(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'wpistic_tm_portal' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-bookings' ) );
			exit;
		}

		if ( isset( $_POST['portal_status'] ) ) {
			$this->bookings->set_portal_status( $id, sanitize_key( wp_unslash( $_POST['portal_status'] ) ) );
		}
		if ( isset( $_POST['assigned_to'] ) ) {
			$this->bookings->assign( $id, absint( $_POST['assigned_to'] ) );
		}
		$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		if ( '' !== $note ) {
			$this->bookings->audit( 'booking', $id, 'note', array( 'note' => $note ) );
		}

		$lifecycle_action = sanitize_key( wp_unslash( $_POST['lifecycle_action'] ?? '' ) );
		if ( '' !== $lifecycle_action ) {
			$ok = $this->bookings->apply_lifecycle_action( $id, $lifecycle_action );
			set_transient(
				'wpistic_tm_notice_' . $id,
				$ok ? __( 'Lifecycle updated.', 'wpistic-tour-manager' ) : __( 'Lifecycle action was not allowed from the current status.', 'wpistic-tour-manager' ),
				600
			);
		}

		$gateway_id = sanitize_key( wp_unslash( $_POST['gateway'] ?? '' ) );
		if ( '' !== $gateway_id ) {
			$gateway = $this->gateways->get( $gateway_id );
			if ( $gateway ) {
				$result = $this->bookings->send_deposit_link( $id, $gateway );
				if ( $result ) {
					set_transient( 'wpistic_tm_link_' . $id, array( 'label' => __( 'Deposit payment link', 'wpistic-tour-manager' ), 'url' => $result->redirectUrl, 'instructions' => $result->instructions ), 600 );
				}
			}
		}

		$balance_gateway_id = sanitize_key( wp_unslash( $_POST['balance_gateway'] ?? '' ) );
		if ( '' !== $balance_gateway_id ) {
			$gateway = $this->gateways->get( $balance_gateway_id );
			if ( $gateway ) {
				$result = $this->bookings->send_balance_link( $id, $gateway );
				if ( $result ) {
					set_transient( 'wpistic_tm_link_' . $id, array( 'label' => __( 'Balance payment link', 'wpistic-tour-manager' ), 'url' => $result->redirectUrl, 'instructions' => $result->instructions ), 600 );
				} else {
					set_transient( 'wpistic_tm_notice_' . $id, __( 'Balance link could not be generated. Confirm the booking has a price and balance amount.', 'wpistic-tour-manager' ), 600 );
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . $id ) );
		exit;
	}

	/**
	 * Manual re-dispatch of this booking's `inquiry.created` event to every
	 * enabled connection. Mirrors the automatic dispatch FormisticIngestion
	 * fires on first ingestion -- ConnectionsManager::dispatch() is
	 * idempotent-safe to call again (it always POSTs and logs a fresh
	 * attempt; it does not re-create the booking or send a duplicate guest
	 * email, since dispatch() only reaches the connections layer, never
	 * BookingService::create() or the guest-facing Notifier).
	 */
	public function resend_connection(): void {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'wpistic_tm_resend_connection_' . $id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}

		$booking = $this->bookings->get( $id );
		if ( $booking ) {
			$this->connections->dispatch( 'inquiry.created', $booking );
			$this->bookings->audit( 'booking', $id, 'manual_resend', array( 'triggered_by' => get_current_user_id() ) );
			set_transient( 'wpistic_tm_notice_' . $id, __( 'Resend dispatched. Check the delivery history below for the result.', 'wpistic-tour-manager' ), 600 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . $id . '&tab=connections' ) );
		exit;
	}
}
