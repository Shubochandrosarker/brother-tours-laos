<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

use Wpistic\TourManager\Booking\BookingService;
use Wpistic\TourManager\Connections\ConnectionsManager;
use Wpistic\TourManager\Payments\GatewayManager;

/**
 * Lightweight inquiry/booking portal (NOT a CRM): dashboard, list, detail with the
 * New -> Reviewed -> Sent -> Closed workflow, assign, notes, deposit-link generation,
 * transactions ledger, audit trail, and CSV export.
 */
final class Portal {

	public function __construct(
		private BookingService $bookings,
		private GatewayManager $gateways,
		private ConnectionsManager $connections
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_post_wpistic_tm_portal', array( $this, 'action' ) );
		add_action( 'admin_post_wpistic_tm_export', array( $this, 'export' ) );
	}

	private function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'wpistic_' . $name;
	}

	public function menu(): void {
		add_menu_page( __( 'Tour Manager', 'wpistic-tour-manager' ), __( 'Tour Manager', 'wpistic-tour-manager' ), 'edit_posts', 'wpistic-tour-manager', array( $this, 'render_dashboard' ), 'dashicons-tickets-alt', 25 );
		add_submenu_page( 'wpistic-tour-manager', __( 'Dashboard', 'wpistic-tour-manager' ), __( 'Dashboard', 'wpistic-tour-manager' ), 'edit_posts', 'wpistic-tour-manager', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'wpistic-tour-manager', __( 'Bookings & Inquiries', 'wpistic-tour-manager' ), __( 'Bookings & Inquiries', 'wpistic-tour-manager' ), 'edit_posts', 'wpistic-tm-bookings', array( $this, 'render_bookings' ) );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		global $wpdb;
		$bookings = $this->table( 'bookings' );

		$by_type   = $wpdb->get_results( "SELECT type, COUNT(*) c FROM {$bookings} GROUP BY type", ARRAY_A ); // phpcs:ignore WordPress.DB
		$by_status = $wpdb->get_results( "SELECT portal_status, COUNT(*) c FROM {$bookings} GROUP BY portal_status", ARRAY_A ); // phpcs:ignore WordPress.DB
		$deposits  = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table( 'transactions' )} WHERE type='deposit' AND status='paid'" ); // phpcs:ignore WordPress.DB
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Manager', 'wpistic-tour-manager' ); ?></h1>
			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0;">
				<?php
				foreach ( (array) $by_type as $row ) {
					$this->stat( ucfirst( str_replace( '_', ' ', (string) $row['type'] ) ), (int) $row['c'] );
				}
				$this->stat( __( 'Deposits paid', 'wpistic-tour-manager' ), (int) $deposits );
				?>
			</div>
			<h2><?php esc_html_e( 'Workflow', 'wpistic-tour-manager' ); ?></h2>
			<p>
				<?php
				foreach ( (array) $by_status as $row ) {
					printf( '<span class="wpistic-portal-status %1$s">%2$s: %3$d</span> ', esc_attr( (string) $row['portal_status'] ), esc_html( ucfirst( (string) $row['portal_status'] ) ), (int) $row['c'] );
				}
				?>
			</p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings' ) ); ?>"><?php esc_html_e( 'Open bookings & inquiries', 'wpistic-tour-manager' ); ?></a></p>
		</div>
		<?php
	}

	private function stat( string $label, int $value ): void {
		echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px 22px;min-width:120px;"><div style="font-size:26px;font-weight:600;">' . (int) $value . '</div><div style="color:#646970;">' . esc_html( $label ) . '</div></div>';
	}

	public function render_bookings(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $view ) {
			$this->render_detail( $view );
			return;
		}
		$this->render_list();
	}

	private function render_list(): void {
		global $wpdb;
		$bookings = $this->table( 'bookings' );
		$type     = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$where    = '';
		if ( '' !== $type ) {
			$where = $wpdb->prepare( ' WHERE type = %s', $type ); // phpcs:ignore WordPress.DB
		}
		$rows = $wpdb->get_results( "SELECT * FROM {$bookings}{$where} ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bookings & Inquiries', 'wpistic-tour-manager' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpistic_tm_export' ), 'wpistic_tm_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'wpistic-tour-manager' ); ?></a>
			</h1>
			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings' ) ); ?>"><?php esc_html_e( 'All', 'wpistic-tour-manager' ); ?></a> | </li>
				<?php foreach ( array( 'build_my_trip', 'booking', 'contact', 'agent', 'inquiry' ) as $t ) : ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings&type=' . $t ) ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $t ) ) ); ?></a> | </li>
				<?php endforeach; ?>
			</ul>
			<table class="widefat striped">
				<thead><tr><th>Ref</th><th>Type</th><th>Name</th><th>Email</th><th>Lifecycle</th><th>Workflow</th><th>Created</th></tr></thead>
				<tbody>
				<?php if ( $rows ) : foreach ( $rows as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . (int) $row['id'] ) ); ?>"><strong><?php echo esc_html( $row['reference'] ); ?></strong></a></td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $row['type'] ) ) ); ?></td>
						<td><?php echo esc_html( (string) $row['customer_name'] ); ?></td>
						<td><?php echo esc_html( (string) $row['customer_email'] ); ?></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $row['status'] ) ) ); ?></td>
						<td><span class="wpistic-portal-status <?php echo esc_attr( (string) $row['portal_status'] ); ?>"><?php echo esc_html( ucfirst( (string) $row['portal_status'] ) ); ?></span></td>
						<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No bookings yet.', 'wpistic-tour-manager' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_detail( int $id ): void {
		global $wpdb;
		$booking = $this->bookings->get( $id );
		if ( ! $booking ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Booking not found.', 'wpistic-tour-manager' ) . '</p></div>';
			return;
		}
		$txns  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table( 'transactions' )} WHERE booking_id = %d ORDER BY id DESC", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$audit = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table( 'audit_log' )} WHERE object_type='booking' AND object_id = %d ORDER BY id DESC LIMIT 50", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$link   = get_transient( 'wpistic_tm_link_' . $id );
		$notice = get_transient( 'wpistic_tm_notice_' . $id );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $booking['reference'] ); ?> · <?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $booking['status'] ) ) ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings' ) ); ?>">&larr; <?php esc_html_e( 'Back', 'wpistic-tour-manager' ); ?></a></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( (string) $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( $link && is_array( $link ) ) : ?>
				<div class="notice notice-success"><p><strong><?php echo esc_html( (string) ( $link['label'] ?? __( 'Payment link', 'wpistic-tour-manager' ) ) ); ?>:</strong>
					<?php if ( ! empty( $link['url'] ) ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a>
					<?php else : ?>
						<br><pre style="white-space:pre-wrap;"><?php echo esc_html( (string) $link['instructions'] ); ?></pre>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
				<div>
					<h2><?php esc_html_e( 'Details', 'wpistic-tour-manager' ); ?></h2>
					<table class="widefat striped"><tbody>
						<?php
						foreach ( array( 'type', 'customer_name', 'customer_email', 'customer_phone', 'customer_country', 'party_adults', 'party_children', 'hotel_pref', 'special_requests', 'currency', 'price_amount', 'deposit_amount', 'balance_amount', 'source_url', 'created_at' ) as $field ) {
							if ( '' !== (string) ( $booking[ $field ] ?? '' ) ) {
								echo '<tr><th style="width:160px;">' . esc_html( ucwords( str_replace( '_', ' ', $field ) ) ) . '</th><td>' . esc_html( (string) $booking[ $field ] ) . '</td></tr>';
							}
						}
						?>
					</tbody></table>

					<h2><?php esc_html_e( 'Transactions', 'wpistic-tour-manager' ); ?></h2>
					<table class="widefat striped"><thead><tr><th>Gateway</th><th>Type</th><th>Amount</th><th>Status</th><th>Ref</th><th>When</th></tr></thead><tbody>
						<?php if ( $txns ) : foreach ( $txns as $t ) : ?>
							<tr><td><?php echo esc_html( $t['gateway'] ); ?></td><td><?php echo esc_html( $t['type'] ); ?></td><td><?php echo esc_html( $t['amount'] . ' ' . $t['currency'] ); ?></td><td><?php echo esc_html( $t['status'] ); ?></td><td><code><?php echo esc_html( (string) $t['gateway_txn_id'] ); ?></code></td><td><?php echo esc_html( $t['created_at'] ); ?></td></tr>
						<?php endforeach; else : ?>
							<tr><td colspan="6"><?php esc_html_e( 'None yet.', 'wpistic-tour-manager' ); ?></td></tr>
						<?php endif; ?>
					</tbody></table>

					<h2><?php esc_html_e( 'Activity', 'wpistic-tour-manager' ); ?></h2>
					<ul>
						<?php foreach ( (array) $audit as $a ) : ?>
							<li><code><?php echo esc_html( $a['created_at'] ); ?></code> — <?php echo esc_html( $a['actor'] . ': ' . $a['action'] ); ?> <?php echo esc_html( (string) $a['detail'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div>
					<h2><?php esc_html_e( 'Actions', 'wpistic-tour-manager' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;padding:16px;">
						<input type="hidden" name="action" value="wpistic_tm_portal">
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<?php wp_nonce_field( 'wpistic_tm_portal' ); ?>

						<p><label><strong><?php esc_html_e( 'Workflow', 'wpistic-tour-manager' ); ?></strong></label><br>
						<select name="portal_status">
							<?php foreach ( array( 'new', 'reviewed', 'sent', 'closed' ) as $s ) : ?>
								<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $booking['portal_status'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
							<?php endforeach; ?>
						</select></p>

						<p><label><strong><?php esc_html_e( 'Assign to', 'wpistic-tour-manager' ); ?></strong></label><br>
						<?php wp_dropdown_users( array( 'name' => 'assigned_to', 'selected' => (int) ( $booking['assigned_to'] ?? 0 ), 'show_option_none' => __( '— None —', 'wpistic-tour-manager' ), 'option_none_value' => 0 ) ); ?></p>

						<p><label><strong><?php esc_html_e( 'Add note', 'wpistic-tour-manager' ); ?></strong></label><br>
						<textarea name="note" rows="3" style="width:100%;"></textarea></p>

						<p><label><strong><?php esc_html_e( 'Lifecycle action', 'wpistic-tour-manager' ); ?></strong></label><br>
						<select name="lifecycle_action">
							<option value=""><?php esc_html_e( 'No lifecycle change', 'wpistic-tour-manager' ); ?></option>
							<?php foreach ( $this->bookings->lifecycle_actions( $id ) as $action => $label ) : ?>
								<option value="<?php echo esc_attr( $action ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></p>

						<p><label><strong><?php esc_html_e( 'Generate deposit link', 'wpistic-tour-manager' ); ?></strong></label><br>
						<select name="gateway">
							<option value=""><?php esc_html_e( '— Select gateway —', 'wpistic-tour-manager' ); ?></option>
							<?php foreach ( $this->gateways->enabled() as $gateway ) : ?>
								<option value="<?php echo esc_attr( $gateway->id() ); ?>"><?php echo esc_html( $gateway->displayName() ); ?></option>
							<?php endforeach; ?>
						</select></p>

						<p><label><strong><?php esc_html_e( 'Generate balance link', 'wpistic-tour-manager' ); ?></strong></label><br>
						<select name="balance_gateway">
							<option value=""><?php esc_html_e( 'No balance link', 'wpistic-tour-manager' ); ?></option>
							<?php foreach ( $this->gateways->enabled() as $gateway ) : ?>
								<option value="<?php echo esc_attr( $gateway->id() ); ?>"><?php echo esc_html( $gateway->displayName() ); ?></option>
							<?php endforeach; ?>
						</select></p>

						<p><label><input type="checkbox" name="send_connection" value="1"> <?php esc_html_e( 'Dispatch to connections (mark sent)', 'wpistic-tour-manager' ); ?></label></p>

						<?php submit_button( __( 'Apply', 'wpistic-tour-manager' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
		delete_transient( 'wpistic_tm_link_' . $id );
		delete_transient( 'wpistic_tm_notice_' . $id );
	}

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

		if ( ! empty( $_POST['send_connection'] ) ) {
			$booking = $this->bookings->get( $id );
			if ( $booking ) {
				$this->connections->dispatch( 'inquiry.created', $booking );
				$this->bookings->set_portal_status( $id, 'sent' );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . $id ) );
		exit;
	}

	public function export(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'wpistic_tm_export' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT reference,type,status,portal_status,customer_name,customer_email,customer_phone,customer_country,currency,price_amount,deposit_amount,created_at FROM {$this->table( 'bookings' )} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=brother-tours-bookings-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Reference', 'Type', 'Lifecycle', 'Workflow', 'Name', 'Email', 'Phone', 'Country', 'Currency', 'Price', 'Deposit', 'Created' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
