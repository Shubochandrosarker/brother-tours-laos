<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

use Wpistic\TourCore\Booking\BookingStatus;
use Wpistic\TourManager\Booking\BookingService;
use Wpistic\TourManager\Connections\ConnectionsManager;
use Wpistic\TourManager\Payments\GatewayManager;

/**
 * Bookings & inquiries list: search, filters, sortable columns, pagination,
 * bulk assign / bulk workflow update, and a CSV export that respects the
 * current filters. Delegates the single-booking view (?view={id}) to
 * BookingDetail, which it owns as a collaborator so Plugin.php only wires
 * this one class in.
 */
final class Bookings {

	private const PER_PAGE = 20;

	private BookingDetail $detail;

	public function __construct(
		private BookingService $bookings,
		private GatewayManager $gateways,
		private ConnectionsManager $connections
	) {
		$this->detail = new BookingDetail( $bookings, $gateways, $connections );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_post_wpistic_tm_export', array( $this, 'export' ) );
		add_action( 'admin_post_wpistic_tm_bulk', array( $this, 'bulk' ) );
		$this->detail->register();
	}

	public function menu(): void {
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Bookings & Inquiries', 'wpistic-tour-manager' ),
			__( 'Bookings & Inquiries', 'wpistic-tour-manager' ),
			'edit_posts',
			'wpistic-tm-bookings',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $view ) {
			$this->detail->render( $view );
			return;
		}
		$this->render_list();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function filters_from_request(): array {
		// Read-only GET filters (search/sort/paging); no nonce required -- these
		// never change data, matching the convention the old Portal used for its
		// type filter. Every value here is bound via $wpdb->prepare() inside
		// BookingService::query()/export_rows(), never concatenated into SQL.
		return array(
			'search'         => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'type'           => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'         => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'portal_status'  => isset( $_GET['portal_status'] ) ? sanitize_key( wp_unslash( $_GET['portal_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'payment_status' => isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'assigned_to'    => isset( $_GET['assigned_to'] ) ? absint( $_GET['assigned_to'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'tour_id'        => isset( $_GET['tour_id'] ) ? absint( $_GET['tour_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby'        => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'          => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'page'           => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'per_page'       => self::PER_PAGE,
		);
	}

	private function render_list(): void {
		$args   = $this->filters_from_request();
		$result = $this->bookings->query( $args );
		?>
		<div class="wrap wpistic-tm-dashboard"<?php echo wp_kses_post( AdminAssets::theme_attr() ); ?>>
			<div class="wpistic-tm-topbar">
				<h1>
					<?php esc_html_e( 'Bookings & Inquiries', 'wpistic-tour-manager' ); ?>
					<a class="page-title-action" href="<?php echo esc_url( $this->export_url( $args ) ); ?>"><?php esc_html_e( 'Export CSV', 'wpistic-tour-manager' ); ?></a>
				</h1>
				<?php AdminAssets::render_theme_toggle(); ?>
			</div>

			<?php $this->render_filters( $args ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpistic-tm-bulk-form">
				<input type="hidden" name="action" value="wpistic_tm_bulk">
				<?php wp_nonce_field( 'wpistic_tm_bulk' ); ?>

				<div class="wpistic-tm-bulkbar">
					<select name="bulk_action">
						<option value=""><?php esc_html_e( 'Bulk actions', 'wpistic-tour-manager' ); ?></option>
						<option value="portal_status"><?php esc_html_e( 'Set workflow status…', 'wpistic-tour-manager' ); ?></option>
						<option value="assign"><?php esc_html_e( 'Assign to…', 'wpistic-tour-manager' ); ?></option>
					</select>
					<select name="bulk_portal_status">
						<?php foreach ( array( 'new', 'reviewed', 'sent', 'closed' ) as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( ucfirst( $s ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'bulk_assigned_to',
							'show_option_none'  => __( '— None —', 'wpistic-tour-manager' ),
							'option_none_value' => 0,
						)
					);
					?>
					<button type="submit" class="button"><?php esc_html_e( 'Apply to selected', 'wpistic-tour-manager' ); ?></button>
				</div>

				<table class="widefat striped wpistic-tm-table" id="wpistic-tm-bookings-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="wpistic-tm-select-all"></td>
							<?php $this->sortable_header( 'reference', __( 'Ref', 'wpistic-tour-manager' ), $args ); ?>
							<th><?php esc_html_e( 'Type', 'wpistic-tour-manager' ); ?></th>
							<?php $this->sortable_header( 'customer_name', __( 'Name', 'wpistic-tour-manager' ), $args ); ?>
							<th><?php esc_html_e( 'Email', 'wpistic-tour-manager' ); ?></th>
							<th><?php esc_html_e( 'Lifecycle', 'wpistic-tour-manager' ); ?></th>
							<th><?php esc_html_e( 'Workflow', 'wpistic-tour-manager' ); ?></th>
							<th><?php esc_html_e( 'Assigned', 'wpistic-tour-manager' ); ?></th>
							<?php $this->sortable_header( 'created_at', __( 'Created', 'wpistic-tour-manager' ), $args ); ?>
						</tr>
					</thead>
					<tbody>
					<?php if ( $result['rows'] ) : ?>
						<?php foreach ( $result['rows'] as $row ) : ?>
							<tr>
								<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $row['id']; ?>"></th>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . (int) $row['id'] ) ); ?>"><strong><?php echo esc_html( (string) $row['reference'] ); ?></strong></a></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $row['type'] ) ) ); ?></td>
								<td><?php echo esc_html( (string) $row['customer_name'] ); ?></td>
								<td><?php echo esc_html( (string) $row['customer_email'] ); ?></td>
								<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $row['status'] ) ) ); ?></td>
								<td><span class="wpistic-tm-badge wpistic-tm-badge--<?php echo esc_attr( (string) $row['portal_status'] ); ?>"><?php echo esc_html( ucfirst( (string) $row['portal_status'] ) ); ?></span></td>
								<td><?php echo esc_html( $row['assigned_to'] ? ( get_userdata( (int) $row['assigned_to'] )->display_name ?? '—' ) : '—' ); ?></td>
								<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="9" class="wpistic-tm-empty"><?php esc_html_e( 'No bookings match your filters.', 'wpistic-tour-manager' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</form>

			<?php $this->render_pagination( $result, $args ); ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function sortable_header( string $key, string $label, array $args ): void {
		$current_order = ( ( $args['orderby'] ?? '' ) === $key );
		$next_dir      = ( $current_order && 'asc' === ( $args['order'] ?? '' ) ) ? 'desc' : 'asc';
		$url           = add_query_arg( array_merge( $this->query_args_for_links( $args ), array( 'orderby' => $key, 'order' => $next_dir ) ), admin_url( 'admin.php' ) );
		$arrow         = $current_order ? ( 'asc' === $args['order'] ? ' ↑' : ' ↓' ) : '';
		echo '<th><a href="' . esc_url( $url ) . '">' . esc_html( $label . $arrow ) . '</a></th>';
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function query_args_for_links( array $args ): array {
		return array(
			'page'           => 'wpistic-tm-bookings',
			's'              => $args['search'],
			'type'           => $args['type'],
			'status'         => $args['status'],
			'portal_status'  => $args['portal_status'],
			'payment_status' => $args['payment_status'],
			'assigned_to'    => $args['assigned_to'] ?: '',
			'tour_id'        => $args['tour_id'] ?: '',
			'date_from'      => $args['date_from'],
			'date_to'        => $args['date_to'],
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function render_filters( array $args ): void {
		$tours = get_posts( array( 'post_type' => 'wpistic_tour', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ) );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="wpistic-tm-filters">
			<input type="hidden" name="page" value="wpistic-tm-bookings">
			<input type="search" name="s" value="<?php echo esc_attr( (string) $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, reference…', 'wpistic-tour-manager' ); ?>">

			<select name="type">
				<option value=""><?php esc_html_e( 'All types', 'wpistic-tour-manager' ); ?></option>
				<?php foreach ( $this->bookings->known_types() as $t ) : ?>
					<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $args['type'], $t ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $t ) ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="status">
				<option value=""><?php esc_html_e( 'All lifecycle stages', 'wpistic-tour-manager' ); ?></option>
				<?php foreach ( BookingStatus::cases() as $status ) : ?>
					<option value="<?php echo esc_attr( $status->value ); ?>" <?php selected( $args['status'], $status->value ); ?>><?php echo esc_html( $status->label() ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="portal_status">
				<option value=""><?php esc_html_e( 'All workflow stages', 'wpistic-tour-manager' ); ?></option>
				<?php foreach ( array( 'new', 'reviewed', 'sent', 'closed' ) as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $args['portal_status'], $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="payment_status">
				<option value=""><?php esc_html_e( 'All payment stages', 'wpistic-tour-manager' ); ?></option>
				<?php foreach ( array_keys( $this->bookings->payment_status_groups() ) as $p ) : ?>
					<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $args['payment_status'], $p ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $p ) ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="tour_id">
				<option value=""><?php esc_html_e( 'All tours', 'wpistic-tour-manager' ); ?></option>
				<?php foreach ( $tours as $tour ) : ?>
					<option value="<?php echo (int) $tour->ID; ?>" <?php selected( (int) $args['tour_id'], $tour->ID ); ?>><?php echo esc_html( $tour->post_title ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php
			// No "Unassigned" option here deliberately: absint() (applied to every
			// $_GET value before it reaches BookingService::query()) cannot
			// distinguish a sentinel negative value from a real user id, and
			// BookingService::build_where() treats assigned_to = 0 as "no filter" --
			// mirroring WP core's own show_option_all value keeps that mapping exact
			// instead of guessing at a magic number.
			wp_dropdown_users(
				array(
					'name'             => 'assigned_to',
					'selected'         => (int) $args['assigned_to'],
					'show_option_all'  => __( 'All staff', 'wpistic-tour-manager' ),
				)
			);
			?>

			<label class="screen-reader-text" for="wpistic-tm-date-from"><?php esc_html_e( 'From date', 'wpistic-tour-manager' ); ?></label>
			<input type="date" id="wpistic-tm-date-from" name="date_from" value="<?php echo esc_attr( (string) $args['date_from'] ); ?>">
			<label class="screen-reader-text" for="wpistic-tm-date-to"><?php esc_html_e( 'To date', 'wpistic-tour-manager' ); ?></label>
			<input type="date" id="wpistic-tm-date-to" name="date_to" value="<?php echo esc_attr( (string) $args['date_to'] ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wpistic-tour-manager' ); ?></button>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tm-bookings' ) ); ?>"><?php esc_html_e( 'Reset', 'wpistic-tour-manager' ); ?></a>
		</form>
		<?php
	}

	/**
	 * @param array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int} $result
	 * @param array<string, mixed> $args
	 */
	private function render_pagination( array $result, array $args ): void {
		$total_pages = (int) max( 1, ceil( $result['total'] / $result['per_page'] ) );
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<div class="wpistic-tm-pagination">';
		printf(
			/* translators: 1: current page, 2: total pages, 3: total results. */
			esc_html__( 'Page %1$d of %2$d (%3$s results)', 'wpistic-tour-manager' ),
			(int) $result['page'],
			$total_pages,
			esc_html( number_format_i18n( $result['total'] ) )
		);
		echo '<span class="wpistic-tm-pagination-links">';
		for ( $p = 1; $p <= $total_pages; $p++ ) {
			$url = add_query_arg( array_merge( $this->query_args_for_links( $args ), array( 'orderby' => $args['orderby'], 'order' => $args['order'], 'paged' => $p ) ), admin_url( 'admin.php' ) );
			$cls = $p === (int) $result['page'] ? ' class="current"' : '';
			echo '<a' . wp_kses_post( $cls ) . ' href="' . esc_url( $url ) . '">' . (int) $p . '</a> ';
		}
		echo '</span></div>';
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function export_url( array $args ): string {
		$url = add_query_arg( $this->query_args_for_links( $args ), admin_url( 'admin-post.php' ) );
		$url = add_query_arg( 'action', 'wpistic_tm_export', $url );
		return wp_nonce_url( $url, 'wpistic_tm_export' );
	}

	/**
	 * CSV export. Respects whatever filters are active on the list screen the
	 * link was generated from (search/type/status/workflow/payment/tour/staff/
	 * date range), rather than always exporting the entire table -- so "export
	 * what I'm looking at" behaves the way a triage-focused export should.
	 */
	public function export(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'wpistic_tm_export' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}

		$args = array(
			'search'         => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'type'           => isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '',
			'status'         => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'portal_status'  => isset( $_GET['portal_status'] ) ? sanitize_key( wp_unslash( $_GET['portal_status'] ) ) : '',
			'payment_status' => isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : '',
			'assigned_to'    => isset( $_GET['assigned_to'] ) ? absint( $_GET['assigned_to'] ) : 0,
			'tour_id'        => isset( $_GET['tour_id'] ) ? absint( $_GET['tour_id'] ) : 0,
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		);

		$rows = $this->bookings->export_rows( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=brother-tours-bookings-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Reference', 'Type', 'Lifecycle', 'Workflow', 'Name', 'Email', 'Phone', 'Country', 'Currency', 'Price', 'Deposit', 'Created' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Bulk assignment / bulk workflow-status update from the list screen's
	 * checkbox selection. Same nonce + capability pattern as every other
	 * state-changing action in this admin; every id is cast with absint()
	 * and bound through BookingService's prepared bulk_* methods.
	 */
	public function bulk(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'wpistic_tm_bulk' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wpistic-tour-manager' ) );
		}

		$ids = array();
		if ( isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ) {
			$ids = array_map( 'absint', wp_unslash( $_POST['ids'] ) ); // phpcs:ignore WordPress.Security.ValidationSanitization
		}

		$bulk_action = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '' ) );

		if ( $ids && 'assign' === $bulk_action ) {
			$this->bookings->bulk_assign( $ids, absint( $_POST['bulk_assigned_to'] ?? 0 ) );
		} elseif ( $ids && 'portal_status' === $bulk_action ) {
			$this->bookings->bulk_set_portal_status( $ids, sanitize_key( wp_unslash( $_POST['bulk_portal_status'] ?? '' ) ) );
		}

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'admin.php?page=wpistic-tm-bookings' ) );
		exit;
	}
}
