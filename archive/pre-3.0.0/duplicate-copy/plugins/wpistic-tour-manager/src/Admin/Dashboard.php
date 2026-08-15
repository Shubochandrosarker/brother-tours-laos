<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Admin;

use Wpistic\TourManager\Booking\BookingService;
use Wpistic\TourManager\Connections\ConnectionsManager;

/**
 * Tour Manager dashboard: KPI cards, a date-range comparison, inquiry-source
 * and pipeline breakdowns (CSS-only bars, no chart library), recent
 * inquiries, upcoming departures, a payment summary, and connection
 * (Tourflows/webhook) integration health.
 *
 * Every KPI's real data source is documented inline next to where it is
 * rendered -- see the individual render_* methods below.
 */
final class Dashboard {

	public function __construct(
		private BookingService $bookings,
		private ConnectionsManager $connections
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Tour Manager', 'wpistic-tour-manager' ),
			__( 'Tour Manager', 'wpistic-tour-manager' ),
			'edit_posts',
			'wpistic-tour-manager',
			array( $this, 'render' ),
			'dashicons-tickets-alt',
			25
		);
		add_submenu_page(
			'wpistic-tour-manager',
			__( 'Dashboard', 'wpistic-tour-manager' ),
			__( 'Dashboard', 'wpistic-tour-manager' ),
			'edit_posts',
			'wpistic-tour-manager',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Read-only GET filter (date range); no state change, so no nonce needed --
		// same convention the old Portal used for its type filter.
		$since = isset( $_GET['since'] ) ? sanitize_text_field( wp_unslash( $_GET['since'] ) ) : gmdate( 'Y-m-d', strtotime( '-29 days' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$until = isset( $_GET['until'] ) ? sanitize_text_field( wp_unslash( $_GET['until'] ) ) : gmdate( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$stats   = $this->bookings->dashboard_stats( $since, $until );
		$since   = (string) $stats['since'];
		$until   = (string) $stats['until'];
		$currency = (string) get_option( 'wpistic_tm_currency', 'USD' );
		?>
		<div class="wrap wpistic-tm-dashboard"<?php echo wp_kses_post( AdminAssets::theme_attr() ); ?>>
			<div class="wpistic-tm-topbar">
				<h1><?php esc_html_e( 'Tour Manager', 'wpistic-tour-manager' ); ?></h1>
				<?php AdminAssets::render_theme_toggle(); ?>
			</div>

			<?php $this->render_period_form( $since, $until ); ?>

			<?php $this->render_kpis( $stats, $currency ); ?>

			<div class="wpistic-tm-grid wpistic-tm-grid--2">
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Inquiry sources', 'wpistic-tour-manager' ); ?></h2>
					<p class="wpistic-tm-card-note"><?php esc_html_e( 'By request type, created in the selected period.', 'wpistic-tour-manager' ); ?></p>
					<?php $this->render_bar_chart( (array) $stats['by_type'], array( $this, 'label_type' ) ); ?>
				</section>
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Booking pipeline', 'wpistic-tour-manager' ); ?></h2>
					<p class="wpistic-tm-card-note"><?php esc_html_e( 'Current triage workflow status, all time.', 'wpistic-tour-manager' ); ?></p>
					<?php $this->render_bar_chart( (array) $stats['by_portal_status'], array( $this, 'label_portal_status' ) ); ?>
				</section>
			</div>

			<div class="wpistic-tm-grid wpistic-tm-grid--2">
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Recent inquiries', 'wpistic-tour-manager' ); ?></h2>
					<?php $this->render_recent_inquiries(); ?>
				</section>
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Upcoming departures', 'wpistic-tour-manager' ); ?></h2>
					<?php $this->render_upcoming_departures(); ?>
				</section>
			</div>

			<div class="wpistic-tm-grid wpistic-tm-grid--2">
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Payment summary', 'wpistic-tour-manager' ); ?></h2>
					<p class="wpistic-tm-card-note"><?php esc_html_e( 'Paid transactions in the selected period, by gateway.', 'wpistic-tour-manager' ); ?></p>
					<?php $this->render_payment_summary( (array) $stats['gateway_breakdown'], $currency ); ?>
				</section>
				<section class="wpistic-tm-card">
					<h2><?php esc_html_e( 'Integration health', 'wpistic-tour-manager' ); ?></h2>
					<p class="wpistic-tm-card-note"><?php esc_html_e( 'Configured connections and their most recent dispatch.', 'wpistic-tour-manager' ); ?></p>
					<?php $this->render_integration_health(); ?>
				</section>
			</div>

			<section class="wpistic-tm-card">
				<h2><?php esc_html_e( 'Quick actions', 'wpistic-tour-manager' ); ?></h2>
				<?php $this->render_quick_actions( $stats ); ?>
			</section>
		</div>
		<?php
	}

	private function render_period_form( string $since, string $until ): void {
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="wpistic-tm-period-form">
			<input type="hidden" name="page" value="wpistic-tour-manager">
			<label>
				<span><?php esc_html_e( 'From', 'wpistic-tour-manager' ); ?></span>
				<input type="date" name="since" value="<?php echo esc_attr( $since ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'To', 'wpistic-tour-manager' ); ?></span>
				<input type="date" name="until" value="<?php echo esc_attr( $until ); ?>">
			</label>
			<button type="submit" class="button"><?php esc_html_e( 'Apply', 'wpistic-tour-manager' ); ?></button>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wpistic-tour-manager' ) ); ?>"><?php esc_html_e( 'Last 30 days', 'wpistic-tour-manager' ); ?></a>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $stats
	 */
	private function render_kpis( array $stats, string $currency ): void {
		$new_inquiries = (array) $stats['new_inquiries'];
		$deposits      = (array) $stats['deposits_paid'];
		$revenue       = (array) $stats['revenue'];
		$failed        = (array) $stats['failed_deliveries'];

		$cards = array(
			array(
				'label' => __( 'New inquiries', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $new_inquiries['value'] ),
				'delta' => $new_inquiries['delta'],
				'note'  => __( 'Created in the selected period.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Awaiting review', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $stats['awaiting_review'] ),
				'delta' => null,
				'note'  => __( 'Workflow: new (queue depth, all time).', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Awaiting customer', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $stats['awaiting_customer'] ),
				'delta' => null,
				'note'  => __( 'Workflow: sent, waiting on a reply.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Open bookings', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $stats['open_bookings'] ),
				'delta' => null,
				'note'  => __( 'Lifecycle not completed/cancelled/refunded/expired.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Confirmed bookings', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $stats['confirmed_bookings'] ),
				'delta' => null,
				'note'  => __( 'Lifecycle: confirmed, balance due, or paid in full.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Upcoming departures', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( $this->upcoming_departure_count() ),
				'delta' => null,
				'note'  => __( 'Departure date on or after today.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Deposits paid', 'wpistic-tour-manager' ),
				'value' => number_format_i18n( (int) $deposits['value'] ),
				'delta' => $deposits['delta'],
				'note'  => __( 'Paid deposit transactions in the period.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Outstanding balances', 'wpistic-tour-manager' ),
				'value' => $this->format_money( (float) $stats['outstanding_balance'], $currency ),
				'delta' => null,
				'note'  => __( 'Sum of balance_amount for bookings awaiting final payment.', 'wpistic-tour-manager' ),
			),
			array(
				'label' => __( 'Revenue', 'wpistic-tour-manager' ),
				'value' => $this->format_money( (float) $revenue['value'], $currency ),
				'delta' => $revenue['delta'],
				'note'  => __( 'Paid deposit + balance transactions in the period. Approximation: sums raw transaction amounts across gateways with no currency conversion.', 'wpistic-tour-manager' ),
			),
			array(
				'label'   => __( 'Failed deliveries', 'wpistic-tour-manager' ),
				'value'   => number_format_i18n( (int) $failed['value'] ),
				'delta'   => $failed['delta'],
				'note'    => __( 'Connection dispatches outside the 2xx range in the period.', 'wpistic-tour-manager' ),
				'warn'    => (int) $failed['value'] > 0,
			),
		);

		echo '<div class="wpistic-tm-kpis">';
		foreach ( $cards as $card ) {
			$class = 'wpistic-tm-kpi' . ( ! empty( $card['warn'] ) ? ' wpistic-tm-kpi--warn' : '' );
			echo '<div class="' . esc_attr( $class ) . '">';
			echo '<div class="wpistic-tm-kpi-label">' . esc_html( (string) $card['label'] ) . '</div>';
			echo '<div class="wpistic-tm-kpi-value">' . esc_html( (string) $card['value'] ) . '</div>';
			if ( null !== $card['delta'] ) {
				$this->render_delta( (float) $card['delta'] );
			}
			echo '<div class="wpistic-tm-kpi-note">' . esc_html( (string) $card['note'] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_delta( float $delta ): void {
		$direction = $delta > 0 ? 'up' : ( $delta < 0 ? 'down' : 'flat' );
		$sign      = $delta > 0 ? '+' : '';
		printf(
			'<div class="wpistic-tm-kpi-delta wpistic-tm-kpi-delta--%1$s">%2$s%3$s%% <span>%4$s</span></div>',
			esc_attr( $direction ),
			esc_html( $sign ),
			esc_html( number_format_i18n( $delta, 1 ) ),
			esc_html__( 'vs. previous period', 'wpistic-tour-manager' )
		);
	}

	private function format_money( float $amount, string $currency ): string {
		return number_format_i18n( $amount, 2 ) . ' ' . $currency;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @param callable $label_for
	 */
	private function render_bar_chart( array $rows, callable $label_for ): void {
		if ( ! $rows ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No data for this period.', 'wpistic-tour-manager' ) . '</p>';
			return;
		}
		$max = 1;
		foreach ( $rows as $row ) {
			$max = max( $max, (int) $row['c'] );
		}
		echo '<div class="wpistic-tm-bars">';
		foreach ( $rows as $row ) {
			$count   = (int) $row['c'];
			$percent = (int) round( ( $count / $max ) * 100 );
			echo '<div class="wpistic-tm-bar-row">';
			echo '<span class="wpistic-tm-bar-label">' . esc_html( (string) call_user_func( $label_for, (string) $row['label'] ) ) . '</span>';
			echo '<span class="wpistic-tm-bar-track"><span class="wpistic-tm-bar-fill" style="width:' . (int) $percent . '%"></span></span>';
			echo '<span class="wpistic-tm-bar-value">' . esc_html( number_format_i18n( $count ) ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	public function label_type( string $type ): string {
		return ucwords( str_replace( '_', ' ', $type ) );
	}

	public function label_portal_status( string $status ): string {
		return ucfirst( $status );
	}

	private function render_recent_inquiries(): void {
		$rows = $this->bookings->recent( 10 );
		if ( ! $rows ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No inquiries yet.', 'wpistic-tour-manager' ) . '</p>';
			return;
		}
		echo '<ul class="wpistic-tm-list">';
		foreach ( $rows as $row ) {
			$url = admin_url( 'admin.php?page=wpistic-tm-bookings&view=' . (int) $row['id'] );
			echo '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( (string) $row['reference'] ) . '</strong></a> ';
			echo '<span class="wpistic-tm-list-meta">' . esc_html( (string) $row['customer_name'] ) . ' &middot; ' . esc_html( ucwords( str_replace( '_', ' ', (string) $row['type'] ) ) ) . ' &middot; ' . esc_html( (string) $row['created_at'] ) . '</span></li>';
		}
		echo '</ul>';
	}

	/**
	 * Upcoming-departures data source: the `wpistic_dep_date` meta field on the
	 * `wpistic_departure` CPT (see Admin\MetaBoxes::box_departure(), upgraded to
	 * a validated HTML5 date input as part of this change). Existing sites must
	 * backfill this field on their existing departure posts -- until then this
	 * KPI and list correctly show zero/empty rather than a fabricated count.
	 */
	private function upcoming_departure_query(): \WP_Query {
		return new \WP_Query(
			array(
				'post_type'      => 'wpistic_departure',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'no_found_rows'  => false,
				'meta_key'       => 'wpistic_dep_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'wpistic_dep_date',
						'value'   => gmdate( 'Y-m-d' ),
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			)
		);
	}

	private function upcoming_departure_count(): int {
		$query = $this->upcoming_departure_query();
		$count = (int) $query->found_posts;
		wp_reset_postdata();
		return $count;
	}

	private function render_upcoming_departures(): void {
		$query = $this->upcoming_departure_query();
		if ( ! $query->have_posts() ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No upcoming departures with a date set. Add a date on each departure post to populate this list.', 'wpistic-tour-manager' ) . '</p>';
			wp_reset_postdata();
			return;
		}
		echo '<ul class="wpistic-tm-list">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$date    = (string) get_post_meta( $post_id, 'wpistic_dep_date', true );
			$tour_id = (int) get_post_meta( $post_id, 'wpistic_dep_tour', true );
			$seats   = (string) get_post_meta( $post_id, 'wpistic_dep_seats_left', true );
			$edit    = get_edit_post_link( $post_id, '' );
			echo '<li><a href="' . esc_url( (string) $edit ) . '"><strong>' . esc_html( $date ) . '</strong></a> ';
			echo '<span class="wpistic-tm-list-meta">' . esc_html( $tour_id ? get_the_title( $tour_id ) : get_the_title() ) . ( '' !== $seats ? ' &middot; ' . esc_html( sprintf( /* translators: %s: seats left. */ __( '%s seats left', 'wpistic-tour-manager' ), $seats ) ) : '' ) . '</span></li>';
		}
		wp_reset_postdata();
		echo '</ul>';
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function render_payment_summary( array $rows, string $currency ): void {
		if ( ! $rows ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No paid transactions in this period.', 'wpistic-tour-manager' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped wpistic-tm-table"><thead><tr><th>' . esc_html__( 'Gateway', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Type', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Count', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Total', 'wpistic-tour-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( ucfirst( (string) $row['gateway'] ) ) . '</td><td>' . esc_html( ucfirst( (string) $row['type'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $row['c'] ) ) . '</td><td>' . esc_html( $this->format_money( (float) $row['total'], $currency ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_integration_health(): void {
		$connections = $this->connections->all();
		if ( ! $connections ) {
			echo '<p class="wpistic-tm-empty">' . esc_html__( 'No connections configured yet.', 'wpistic-tour-manager' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wpistic-tm-connections' ) ) . '">' . esc_html__( 'Add one', 'wpistic-tour-manager' ) . '</a></p>';
			return;
		}

		$dispatch_log = $this->bookings->connection_dispatch_log( 200 );
		$last_by_connection = array();
		foreach ( $dispatch_log as $entry ) {
			$connection_id = (int) ( $entry['detail']['connection_id'] ?? 0 );
			if ( $connection_id && ! isset( $last_by_connection[ $connection_id ] ) ) {
				$last_by_connection[ $connection_id ] = $entry;
			}
		}

		echo '<table class="widefat striped wpistic-tm-table"><thead><tr><th>' . esc_html__( 'Connection', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Status', 'wpistic-tour-manager' ) . '</th><th>' . esc_html__( 'Last dispatch', 'wpistic-tour-manager' ) . '</th></tr></thead><tbody>';
		foreach ( $connections as $connection ) {
			$id      = (int) $connection['id'];
			$last    = $last_by_connection[ $id ] ?? null;
			$enabled = ! empty( $connection['enabled'] );
			echo '<tr><td>' . esc_html( (string) $connection['name'] ) . '</td>';
			echo '<td>' . ( $enabled ? '<span class="wpistic-tm-badge wpistic-tm-badge--success">' . esc_html__( 'Enabled', 'wpistic-tour-manager' ) . '</span>' : '<span class="wpistic-tm-badge">' . esc_html__( 'Disabled', 'wpistic-tour-manager' ) . '</span>' ) . '</td>';
			if ( $last ) {
				$code    = (int) ( $last['detail']['status_code'] ?? 0 );
				$ok      = $code >= 200 && $code < 300;
				$badge   = $ok ? 'wpistic-tm-badge--success' : 'wpistic-tm-badge--danger';
				echo '<td><span class="wpistic-tm-badge ' . esc_attr( $badge ) . '">' . esc_html( (string) $code ) . '</span> <span class="wpistic-tm-list-meta">' . esc_html( (string) $last['created_at'] ) . '</span></td>';
			} else {
				echo '<td><span class="wpistic-tm-empty">' . esc_html__( 'No dispatches yet', 'wpistic-tour-manager' ) . '</span></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param array<string, mixed> $stats
	 */
	private function render_quick_actions( array $stats ): void {
		$actions = array(
			array( 'label' => __( 'Review new inquiries', 'wpistic-tour-manager' ), 'query' => 'portal_status=new' ),
			array( 'label' => __( 'Awaiting customer reply', 'wpistic-tour-manager' ), 'query' => 'portal_status=sent' ),
			array( 'label' => __( 'Balance due', 'wpistic-tour-manager' ), 'query' => 'status=balance_due' ),
			array( 'label' => __( 'Export all bookings (CSV)', 'wpistic-tour-manager' ), 'query' => '', 'export' => true ),
		);
		echo '<div class="wpistic-tm-actions">';
		foreach ( $actions as $action ) {
			if ( ! empty( $action['export'] ) ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpistic_tm_export' ), 'wpistic_tm_export' );
			} else {
				$url = admin_url( 'admin.php?page=wpistic-tm-bookings' . ( '' !== $action['query'] ? '&' . $action['query'] : '' ) );
			}
			echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( (string) $action['label'] ) . '</a>';
		}
		echo '</div>';
	}
}
