<?php
/**
 * Reports REST controller — /g2a-crm/v1/reports/dashboard
 *
 * Phase 1 only ships the dashboard summary; the rest of /reports/* arrives in
 * Phase 5.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Reports_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/reports/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_reports' ),
			)
		);

		foreach ( array( 'revenue', 'pipeline', 'operations', 'membership', 'staff', 'communications' ) as $report ) {
			register_rest_route(
				$this->namespace,
				'/reports/' . $report,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'report_' . $report ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_reports' ),
					'args'                => array(
						'from' => array( 'type' => 'string' ),
						'to'   => array( 'type' => 'string' ),
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			'/reports/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_export_data' ),
			)
		);
	}

	public function report_revenue( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::revenue( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}
	public function report_pipeline( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::pipeline( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}
	public function report_operations( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::operations( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}
	public function report_membership( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::membership_health( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}
	public function report_staff( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::staff( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}
	public function report_communications( WP_REST_Request $request ) {
		return $this->ok( G2A_CRM_Reports_Service::communications( (string) $request->get_param( 'from' ), (string) $request->get_param( 'to' ) ) );
	}

	public function export( WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( ! G2A_CRM_CSV_Exporter::is_exportable( $type ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Unknown export type.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		G2A_CRM_Audit_Log::record( 'export.download', 'export', 0, null, array( 'type' => $type, 'from' => (string) $request->get_param( 'from' ), 'to' => (string) $request->get_param( 'to' ) ) );

		G2A_CRM_CSV_Exporter::stream(
			$type,
			array(
				'from' => (string) $request->get_param( 'from' ),
				'to'   => (string) $request->get_param( 'to' ),
			)
		);
		exit; // unreachable — stream() exits.
	}

	public function dashboard() {
		global $wpdb;

		$customers_table   = G2A_CRM_Customers_Repository::table();
		$leads_table       = G2A_CRM_Leads_Repository::table();
		$tasks_table       = G2A_CRM_Tasks_Repository::table();
		$bookings_table    = G2A_CRM_Bookings_Repository::table();
		$waivers_table     = G2A_CRM_Waivers_Repository::table();
		$memberships_table = G2A_CRM_Memberships_Repository::table();
		$classes_table     = G2A_CRM_Classes_Repository::table();
		$students_table    = G2A_CRM_Class_Students_Repository::table();

		$now             = g2a_crm_now();
		$week_ago        = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$start_of_day    = gmdate( 'Y-m-d 00:00:00' );
		$end_of_day      = gmdate( 'Y-m-d 23:59:59' );
		$expiry_horizon  = gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) );
		$renewal_horizon = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		$customers_total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers_table}" );
		$customers_active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$customers_table} WHERE status = %s", 'active' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$leads_new_week   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table} WHERE created_at >= %s", $week_ago ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$leads_open       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table} WHERE status NOT IN (%s,%s,%s,%s)", 'converted', 'lost', 'not_a_fit', 'archived' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$tasks_open       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tasks_table} WHERE status IN (%s,%s,%s)", 'open', 'in_progress', 'waiting' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tasks_overdue    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tasks_table} WHERE status NOT IN (%s,%s) AND due_at IS NOT NULL AND due_at < %s", 'completed', 'cancelled', $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tasks_mine_open  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tasks_table} WHERE assigned_to = %d AND status IN (%s,%s,%s)", get_current_user_id(), 'open', 'in_progress', 'waiting' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$bookings_today   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE start_time BETWEEN %s AND %s AND status NOT IN (%s,%s)", $start_of_day, $end_of_day, 'cancelled', 'no_show' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bookings_pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = %s AND start_time >= %s", 'pending', $start_of_day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$waivers_needs_verification = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$waivers_table} WHERE status = %s", 'submitted' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$waivers_expiring_soon      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$waivers_table} WHERE status IN (%s,%s) AND expires_at IS NOT NULL AND expires_at BETWEEN %s AND %s", 'submitted', 'verified', $now, $expiry_horizon ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$members_active        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$memberships_table} WHERE status IN (%s,%s,%s)", 'active', 'trial', 'vip' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$members_past_due      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$memberships_table} WHERE status = %s", 'past_due' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$renewals_due          = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$memberships_table} WHERE status IN (%s,%s,%s) AND renewal_date IS NOT NULL AND renewal_date BETWEEN %s AND %s", 'active', 'trial', 'past_due', gmdate( 'Y-m-d' ), $renewal_horizon ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cancellations_30d     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$memberships_table} WHERE status = %s AND cancelled_at IS NOT NULL AND cancelled_at >= %s", 'cancelled', $thirty_days_ago ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$classes_today_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$classes_table} WHERE status NOT IN (%s) AND start_time BETWEEN %s AND %s", 'cancelled', $start_of_day, $end_of_day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$classes_upcoming_week = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$classes_table} WHERE status NOT IN (%s) AND start_time BETWEEN %s AND %s", 'cancelled', $now, gmdate( 'Y-m-d 23:59:59', strtotime( '+7 days' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$msg_stats = G2A_CRM_Messages_Repository::stats();

		// Leads by source (top 5) for the lead-pipeline card.
		$leads_by_source = $wpdb->get_results(
			"SELECT source, COUNT(*) AS cnt FROM {$leads_table} WHERE source <> '' GROUP BY source ORDER BY cnt DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		// Today's upcoming bookings — limit 10 for the "Today's Operations" card.
		$today_bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.customer_id, b.start_time, b.end_time, b.booking_type, b.status, c.first_name, c.last_name, c.waiver_status FROM {$bookings_table} b LEFT JOIN {$customers_table} c ON c.id = b.customer_id WHERE b.start_time BETWEEN %s AND %s AND b.status NOT IN ('cancelled','no_show') ORDER BY b.start_time ASC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start_of_day,
				$end_of_day
			),
			ARRAY_A
		);

		// Today's classes — schedule for the instructor side of the dashboard.
		$today_classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cl.id, cl.name, cl.class_type, cl.start_time, cl.end_time, cl.capacity, cl.status, cl.instructor_id, (SELECT COUNT(*) FROM {$students_table} cs WHERE cs.class_id = cl.id AND cs.status NOT IN ('cancelled')) AS enrolled FROM {$classes_table} cl WHERE cl.start_time BETWEEN %s AND %s AND cl.status NOT IN ('cancelled') ORDER BY cl.start_time ASC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start_of_day,
				$end_of_day
			),
			ARRAY_A
		);

		return $this->ok(
			array(
				'generated_at' => $now,
				'cards'        => array(
					'customers_total'              => $customers_total,
					'customers_active'             => $customers_active,
					'leads_new_week'               => $leads_new_week,
					'leads_open'                   => $leads_open,
					'tasks_open'                   => $tasks_open,
					'tasks_overdue'                => $tasks_overdue,
					'tasks_mine_open'              => $tasks_mine_open,
					'bookings_today'               => $bookings_today,
					'bookings_pending'             => $bookings_pending,
					'waivers_needs_verification'   => $waivers_needs_verification,
					'waivers_expiring_soon'        => $waivers_expiring_soon,
					'members_active'               => $members_active,
					'members_past_due'             => $members_past_due,
					'renewals_due'                 => $renewals_due,
					'cancellations_30d'            => $cancellations_30d,
					'classes_today'                => $classes_today_count,
					'classes_upcoming_week'        => $classes_upcoming_week,
					'messages_queued'              => $msg_stats['queued_total'],
					'messages_sent_today'          => $msg_stats['sent_today'],
					'messages_failed_24h'          => $msg_stats['failed_24h'],
				),
				'leads_by_source' => $leads_by_source ?: array(),
				'today_bookings'  => $today_bookings ?: array(),
				'today_classes'   => $today_classes ?: array(),
			)
		);
	}
}
