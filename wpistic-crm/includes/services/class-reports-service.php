<?php
/**
 * Reports service — pure aggregation queries off the existing schema.
 *
 * No writes, no side effects. Each method returns a plain array suitable for
 * JSON encoding. Date ranges default to the last 30 days unless overridden.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Reports_Service {

	/**
	 * Parse from/to range. Both inputs are sanitized; defaults: last 30 days.
	 *
	 * @return array{from:string,to:string,from_date:string,to_date:string,days:int}
	 */
	public static function parse_range( $from, $to ) {
		$to_ts   = $to   ? strtotime( (string) $to )   : time();
		$from_ts = $from ? strtotime( (string) $from ) : ( $to_ts - 30 * DAY_IN_SECONDS );
		if ( $from_ts > $to_ts ) {
			$tmp     = $from_ts;
			$from_ts = $to_ts;
			$to_ts   = $tmp;
		}
		return array(
			'from'      => gmdate( 'Y-m-d 00:00:00', $from_ts ),
			'to'        => gmdate( 'Y-m-d 23:59:59', $to_ts ),
			'from_date' => gmdate( 'Y-m-d', $from_ts ),
			'to_date'   => gmdate( 'Y-m-d', $to_ts ),
			'days'      => max( 1, (int) ceil( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) + 1 ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Revenue
	 * --------------------------------------------------------------------- */

	public static function revenue( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$bookings_table = G2A_CRM_Bookings_Repository::table();
		$classes_table  = G2A_CRM_Classes_Repository::table();
		$students_table = G2A_CRM_Class_Students_Repository::table();
		$memberships_table = G2A_CRM_Memberships_Repository::table();

		$booking_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM {$bookings_table} WHERE payment_status = 'paid' AND start_time BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);

		// Class revenue = price × students who paid + attended.
		$class_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(cl.price),0) FROM {$students_table} cs JOIN {$classes_table} cl ON cl.id = cs.class_id WHERE cs.payment_status IN ('paid','partial') AND cs.status IN ('attended','completed','checked_in') AND cl.start_time BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);

		$mrr           = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(monthly_amount),0) FROM {$memberships_table} WHERE status IN ('active','trial','vip')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$active_members = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$memberships_table} WHERE status IN ('active','trial','vip')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Daily revenue series for sparkline (last N days).
		$series = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(start_time) AS d, COALESCE(SUM(amount),0) AS amount FROM {$bookings_table} WHERE payment_status = 'paid' AND start_time BETWEEN %s AND %s GROUP BY DATE(start_time) ORDER BY d ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Top classes by revenue.
		$top_classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cl.id, cl.name, cl.class_type, COUNT(cs.id) AS students, SUM(cl.price) AS revenue FROM {$classes_table} cl JOIN {$students_table} cs ON cs.class_id = cl.id WHERE cs.status IN ('attended','completed','checked_in') AND cl.start_time BETWEEN %s AND %s GROUP BY cl.id ORDER BY revenue DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		return array(
			'range'           => $r,
			'totals'          => array(
				'booking_revenue'   => round( $booking_revenue, 2 ),
				'class_revenue'     => round( $class_revenue, 2 ),
				'mrr_current'       => round( $mrr, 2 ),
				'active_members'    => $active_members,
				'total_in_range'    => round( $booking_revenue + $class_revenue, 2 ),
			),
			'daily_series'    => $series ?: array(),
			'top_classes'     => $top_classes ?: array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Pipeline (leads + conversion + customer lifetime value)
	 * --------------------------------------------------------------------- */

	public static function pipeline( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$leads_table = G2A_CRM_Leads_Repository::table();

		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$leads_table} WHERE created_at BETWEEN %s AND %s GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		$by_source = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, COUNT(*) AS leads, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted FROM {$leads_table} WHERE created_at BETWEEN %s AND %s GROUP BY source ORDER BY leads DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);
		foreach ( $by_source as &$row ) {
			$row['conversion_rate'] = $row['leads'] > 0 ? round( 100 * $row['converted'] / $row['leads'], 1 ) : 0;
		}

		$by_interest = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT interest_type, COUNT(*) AS leads, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted FROM {$leads_table} WHERE created_at BETWEEN %s AND %s GROUP BY interest_type ORDER BY leads DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted, SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost FROM {$leads_table} WHERE created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Lead score distribution (buckets).
		$score_distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE
						WHEN lead_score = 0 THEN 'unscored'
						WHEN lead_score BETWEEN 1 AND 25 THEN 'cold'
						WHEN lead_score BETWEEN 26 AND 50 THEN 'warm'
						WHEN lead_score BETWEEN 51 AND 75 THEN 'hot'
						ELSE 'on_fire'
					END AS bucket,
					COUNT(*) AS cnt
				FROM {$leads_table} WHERE created_at BETWEEN %s AND %s GROUP BY bucket", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		return array(
			'range'              => $r,
			'totals'             => array(
				'total'           => (int) ( $totals['total'] ?? 0 ),
				'converted'       => (int) ( $totals['converted'] ?? 0 ),
				'lost'            => (int) ( $totals['lost'] ?? 0 ),
				'conversion_rate' => ( $totals['total'] ?? 0 ) > 0 ? round( 100 * ( $totals['converted'] ?? 0 ) / $totals['total'], 1 ) : 0,
			),
			'by_status'          => $by_status ?: array(),
			'by_source'          => $by_source ?: array(),
			'by_interest'        => $by_interest ?: array(),
			'score_distribution' => $score_distribution ?: array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Operations (utilization, no-show, waiver completion)
	 * --------------------------------------------------------------------- */

	public static function operations( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$bookings_table = G2A_CRM_Bookings_Repository::table();
		$waivers_table  = G2A_CRM_Waivers_Repository::table();

		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$bookings_table} WHERE start_time BETWEEN %s AND %s GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		$total       = 0;
		$no_shows    = 0;
		$completed   = 0;
		foreach ( $by_status as $row ) {
			$total += (int) $row['cnt'];
			if ( 'no_show' === $row['status'] ) {
				$no_shows = (int) $row['cnt'];
			}
			if ( in_array( $row['status'], array( 'completed', 'checked_in' ), true ) ) {
				$completed += (int) $row['cnt'];
			}
		}
		$attended_bookings = $completed; // checked-in + completed both count toward "showed up"
		$considered        = $attended_bookings + $no_shows; // ignore pending/cancelled in this rate

		// Utilization per resource (e.g. lane #).
		$utilization = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resource_type, resource_id, COUNT(*) AS bookings, SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) AS minutes FROM {$bookings_table} WHERE resource_type <> 'none' AND status NOT IN ('cancelled','no_show') AND start_time BETWEEN %s AND %s GROUP BY resource_type, resource_id ORDER BY minutes DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Waiver completion: how many distinct customers signed in range?
		$waivers_submitted = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT customer_id) FROM {$waivers_table} WHERE signed_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);
		$waivers_verified = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$waivers_table} WHERE status = 'verified' AND verified_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);
		$waivers_pending = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$waivers_table} WHERE status = 'submitted'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Bookings per day series.
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(start_time) AS d, COUNT(*) AS cnt, SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_shows FROM {$bookings_table} WHERE start_time BETWEEN %s AND %s GROUP BY DATE(start_time) ORDER BY d ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		return array(
			'range'           => $r,
			'by_status'       => $by_status ?: array(),
			'totals'          => array(
				'total'           => $total,
				'no_shows'        => $no_shows,
				'attended'        => $attended_bookings,
				'no_show_rate'    => $considered > 0 ? round( 100 * $no_shows / $considered, 1 ) : 0,
				'waivers_submitted' => $waivers_submitted,
				'waivers_verified'  => $waivers_verified,
				'waivers_pending'   => $waivers_pending,
				'verify_rate'       => $waivers_submitted > 0 ? round( 100 * $waivers_verified / $waivers_submitted, 1 ) : 0,
			),
			'utilization'     => $utilization ?: array(),
			'daily'           => $daily ?: array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Membership health (MRR, churn, by plan)
	 * --------------------------------------------------------------------- */

	public static function membership_health( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$memberships_table = G2A_CRM_Memberships_Repository::table();

		$by_status = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt, COALESCE(SUM(monthly_amount),0) AS mrr FROM {$memberships_table} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$by_plan = $wpdb->get_results(
			"SELECT plan_name, COUNT(*) AS cnt, COALESCE(SUM(monthly_amount),0) AS mrr FROM {$memberships_table} WHERE status IN ('active','trial','vip') GROUP BY plan_name ORDER BY cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$active_members = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$memberships_table} WHERE status IN ('active','trial','vip')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$mrr = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(monthly_amount),0) FROM {$memberships_table} WHERE status IN ('active','trial','vip')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Cancellations in range — churn signal.
		$cancellations = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$memberships_table} WHERE cancelled_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);
		$new_in_range = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$memberships_table} WHERE created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			)
		);

		// Net new = new - cancellations. Churn rate = cancellations / avg active.
		$churn_rate = $active_members > 0 ? round( 100 * $cancellations / max( 1, $active_members + $cancellations ), 1 ) : 0;

		// Cancellation reasons.
		$reasons = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cancellation_reason, COUNT(*) AS cnt FROM {$memberships_table} WHERE cancelled_at BETWEEN %s AND %s AND cancellation_reason IS NOT NULL AND cancellation_reason <> '' GROUP BY cancellation_reason ORDER BY cnt DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Upcoming renewals (next 30 days).
		$renewals_30d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$memberships_table} WHERE status IN ('active','trial') AND renewal_date IS NOT NULL AND renewal_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d' ),
				gmdate( 'Y-m-d', strtotime( '+30 days' ) )
			)
		);

		return array(
			'range'              => $r,
			'totals'             => array(
				'active_members'  => $active_members,
				'mrr'             => round( $mrr, 2 ),
				'cancellations'   => $cancellations,
				'new_in_range'    => $new_in_range,
				'net_change'      => $new_in_range - $cancellations,
				'churn_rate_pct'  => $churn_rate,
				'renewals_30d'    => $renewals_30d,
			),
			'by_status'          => $by_status ?: array(),
			'by_plan'            => $by_plan ?: array(),
			'cancellation_reasons' => $reasons ?: array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Staff performance
	 * --------------------------------------------------------------------- */

	public static function staff( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$tasks_table    = G2A_CRM_Tasks_Repository::table();
		$bookings_table = G2A_CRM_Bookings_Repository::table();
		$leads_table    = G2A_CRM_Leads_Repository::table();
		$classes_table  = G2A_CRM_Classes_Repository::table();

		// Tasks per staff (created in range OR currently assigned).
		$task_perf = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT assigned_to, COUNT(*) AS total,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
					SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) AS overdue,
					SUM(CASE WHEN status IN ('open','in_progress','waiting') THEN 1 ELSE 0 END) AS open_count
				FROM {$tasks_table}
				WHERE assigned_to IS NOT NULL AND (created_at BETWEEN %s AND %s OR status IN ('open','in_progress','waiting','overdue'))
				GROUP BY assigned_to ORDER BY completed DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Bookings handled per staff in range.
		$booking_perf = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT staff_id AS user_id, COUNT(*) AS bookings FROM {$bookings_table} WHERE staff_id IS NOT NULL AND start_time BETWEEN %s AND %s GROUP BY staff_id ORDER BY bookings DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Leads assigned per staff in range.
		$lead_perf = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT assigned_to AS user_id, COUNT(*) AS leads, SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted FROM {$leads_table} WHERE assigned_to IS NOT NULL AND created_at BETWEEN %s AND %s GROUP BY assigned_to ORDER BY converted DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Instructor load.
		$instructor_load = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT instructor_id AS user_id, COUNT(*) AS classes FROM {$classes_table} WHERE instructor_id IS NOT NULL AND start_time BETWEEN %s AND %s GROUP BY instructor_id ORDER BY classes DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Resolve display names from WP users.
		$rows  = array();
		$index = array();
		foreach ( array( 'task_perf', 'booking_perf', 'lead_perf', 'instructor_load' ) as $bucket ) {
			$source = $$bucket;
			foreach ( $source as $row ) {
				$uid = (int) ( $row['assigned_to'] ?? $row['user_id'] ?? 0 );
				if ( ! $uid ) {
					continue;
				}
				if ( ! isset( $index[ $uid ] ) ) {
					$index[ $uid ] = count( $rows );
					$user          = get_userdata( $uid );
					$rows[]        = array(
						'user_id'         => $uid,
						'name'            => $user ? $user->display_name : "User #{$uid}",
						'tasks_total'     => 0,
						'tasks_completed' => 0,
						'tasks_overdue'   => 0,
						'tasks_open'      => 0,
						'bookings'        => 0,
						'leads'           => 0,
						'leads_converted' => 0,
						'classes'         => 0,
					);
				}
				$idx = $index[ $uid ];
				if ( 'task_perf' === $bucket ) {
					$rows[ $idx ]['tasks_total']     = (int) $row['total'];
					$rows[ $idx ]['tasks_completed'] = (int) $row['completed'];
					$rows[ $idx ]['tasks_overdue']   = (int) $row['overdue'];
					$rows[ $idx ]['tasks_open']      = (int) $row['open_count'];
				} elseif ( 'booking_perf' === $bucket ) {
					$rows[ $idx ]['bookings'] = (int) $row['bookings'];
				} elseif ( 'lead_perf' === $bucket ) {
					$rows[ $idx ]['leads']           = (int) $row['leads'];
					$rows[ $idx ]['leads_converted'] = (int) $row['converted'];
				} elseif ( 'instructor_load' === $bucket ) {
					$rows[ $idx ]['classes'] = (int) $row['classes'];
				}
			}
		}

		return array(
			'range' => $r,
			'staff' => $rows,
		);
	}

	/* ---------------------------------------------------------------------
	 * Communications (delivery rate, failure reasons, channel breakdown)
	 * --------------------------------------------------------------------- */

	public static function communications( $from, $to ) {
		global $wpdb;
		$r = self::parse_range( $from, $to );

		$messages_table = G2A_CRM_Messages_Repository::table();

		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS cnt FROM {$messages_table} WHERE created_at BETWEEN %s AND %s GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);
		$by_channel = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT channel, COUNT(*) AS total,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
					SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped
				FROM {$messages_table} WHERE created_at BETWEEN %s AND %s GROUP BY channel", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);
		foreach ( $by_channel as &$row ) {
			$row['delivery_rate'] = $row['total'] > 0 ? round( 100 * $row['sent'] / $row['total'], 1 ) : 0;
		}

		// Top failure reasons (truncate to keep group buckets small).
		$failures = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUBSTRING(error_text, 1, 80) AS error, COUNT(*) AS cnt FROM {$messages_table} WHERE status = 'failed' AND error_text IS NOT NULL AND created_at BETWEEN %s AND %s GROUP BY SUBSTRING(error_text, 1, 80) ORDER BY cnt DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent FROM {$messages_table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY d ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$r['from'],
				$r['to']
			),
			ARRAY_A
		);

		// Opt-out totals (current state, not range-bound).
		$customers_table = G2A_CRM_Customers_Repository::table();
		$opted_out = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN email_opt_in = 0 THEN 1 ELSE 0 END) AS email_out,
				SUM(CASE WHEN sms_opt_in = 0 THEN 1 ELSE 0 END) AS sms_out,
				COUNT(*) AS total
			FROM {$customers_table}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return array(
			'range'      => $r,
			'by_status'  => $by_status ?: array(),
			'by_channel' => $by_channel ?: array(),
			'failures'   => $failures ?: array(),
			'daily'      => $daily ?: array(),
			'opted_out'  => $opted_out ?: array( 'email_out' => 0, 'sms_out' => 0, 'total' => 0 ),
		);
	}
}
