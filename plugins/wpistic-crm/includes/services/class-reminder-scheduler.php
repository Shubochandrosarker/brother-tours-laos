<?php
/**
 * Scheduled reminders.
 *
 * Phase 2 queues messages into wp_g2a_crm_messages with status = "queued" so the
 * data trail is complete. Phase 4 (Communication Center) will pick up queued
 * rows and actually dispatch via SMTP / SMS providers.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Reminder_Scheduler {

	const HOOK_HOURLY = 'g2a_crm_cron_hourly';
	const HOOK_DAILY  = 'g2a_crm_cron_daily';

	public static function boot() {
		add_action( self::HOOK_HOURLY, array( __CLASS__, 'run_hourly' ) );
		add_action( self::HOOK_DAILY, array( __CLASS__, 'run_daily' ) );

		if ( ! wp_next_scheduled( self::HOOK_HOURLY ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK_HOURLY );
		}
		if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:00' ), 'daily', self::HOOK_DAILY );
		}
	}

	public static function unschedule() {
		foreach ( array( self::HOOK_HOURLY, self::HOOK_DAILY ) as $hook ) {
			$next = wp_next_scheduled( $hook );
			while ( $next ) {
				wp_unschedule_event( $next, $hook );
				$next = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Hourly: queue upcoming booking + class reminders, run class-completion follow-up.
	 */
	public static function run_hourly() {
		self::queue_booking_reminders();
		self::queue_class_reminders();
		self::auto_complete_classes();
	}

	/**
	 * Daily: expire waivers + memberships, queue waiver and renewal warnings, mark overdue tasks.
	 */
	public static function run_daily() {
		G2A_CRM_Waivers_Repository::expire_due();
		G2A_CRM_Memberships_Repository::expire_due();
		self::queue_waiver_expiry_warnings();
		self::queue_renewal_warnings();
		self::mark_overdue_tasks();
	}

	private static function queue_booking_reminders() {
		global $wpdb;
		$lead_hours = (int) G2A_CRM_Settings::get( 'booking_reminder_lead_hours', 24 );

		$window_start = gmdate( 'Y-m-d H:i:s', strtotime( "+{$lead_hours} hours" ) );
		$window_end   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . ( $lead_hours + 1 ) . ' hours' ) );

		$bookings_table = G2A_CRM_Bookings_Repository::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, start_time, booking_type FROM {$bookings_table} WHERE status IN ('pending','confirmed') AND start_time BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$window_start,
				$window_end
			),
			ARRAY_A
		);

		$template = G2A_CRM_Message_Templates_Repository::find_by_slug( 'booking_reminder' );

		foreach ( $rows ?: array() as $booking ) {
			$start_dt = strtotime( $booking['start_time'] );
			$queued   = G2A_CRM_Messages_Repository::queue( array(
				'customer_id' => (int) $booking['customer_id'],
				'channel'     => 'reminder',
				'template_id' => $template ? (int) $template['id'] : null,
				'subject'     => $template ? $template['subject'] : sprintf( /* translators: %s: booking type */ __( 'Reminder: upcoming %s booking', 'guns2ammo-crm' ), $booking['booking_type'] ),
				'body'        => $template ? $template['body'] : sprintf(
					/* translators: 1: booking type, 2: time */
					__( "Reminder: your %1\$s booking is scheduled for %2\$s. Please arrive 10 minutes early. Bring a valid waiver if you don't have one on file.", 'guns2ammo-crm' ),
					$booking['booking_type'],
					$booking['start_time']
				),
				'dedup_key'   => 'booking_reminder:' . $booking['id'],
				// The dispatcher renders these placeholders from a customer context;
				// extras need to live in body/subject (or we'd extend the queue API).
				// For now, include booking metadata via simple substitution at queue time.
			) );

			if ( is_wp_error( $queued ) ) {
				continue;
			}

			// Inline-substitute the booking-specific vars now (renderer handles customer vars later).
			if ( ! empty( $queued['id'] ) && $template ) {
				$inline = array(
					'booking_type' => $booking['booking_type'],
					'booking_time' => gmdate( 'g:i A', $start_dt ),
					'booking_date' => gmdate( 'M j, Y', $start_dt ),
				);
				self::pre_substitute_body( (int) $queued['id'], $inline );
			}
		}
	}

	private static function queue_class_reminders() {
		global $wpdb;
		$lead_hours = (int) G2A_CRM_Settings::get( 'class_reminder_lead_hours', 24 );

		$window_start = gmdate( 'Y-m-d H:i:s', strtotime( "+{$lead_hours} hours" ) );
		$window_end   = gmdate( 'Y-m-d H:i:s', strtotime( '+' . ( $lead_hours + 1 ) . ' hours' ) );

		$classes_table  = G2A_CRM_Classes_Repository::table();
		$students_table = G2A_CRM_Class_Students_Repository::table();
		$template       = G2A_CRM_Message_Templates_Repository::find_by_slug( 'class_reminder' );

		$classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, start_time FROM {$classes_table} WHERE status IN ('scheduled') AND start_time BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$window_start,
				$window_end
			),
			ARRAY_A
		);

		foreach ( $classes ?: array() as $class ) {
			$students = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT customer_id FROM {$students_table} WHERE class_id = %d AND status NOT IN ('cancelled','no_show')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $class['id']
				),
				ARRAY_A
			);
			foreach ( $students ?: array() as $s ) {
				$queued = G2A_CRM_Messages_Repository::queue( array(
					'customer_id' => (int) $s['customer_id'],
					'channel'     => 'reminder',
					'template_id' => $template ? (int) $template['id'] : null,
					'subject'     => $template ? $template['subject'] : sprintf( __( 'Reminder: %s tomorrow', 'guns2ammo-crm' ), $class['name'] ),
					'body'        => $template ? $template['body']    : sprintf(
						__( "Reminder: your class '%1\$s' starts at %2\$s. Arrive 15 minutes early to sign in.", 'guns2ammo-crm' ),
						$class['name'],
						$class['start_time']
					),
					'dedup_key'   => 'class_reminder:' . $class['id'] . ':' . $s['customer_id'],
				) );
				if ( is_wp_error( $queued ) || empty( $queued['id'] ) || ! $template ) {
					continue;
				}
				self::pre_substitute_body( (int) $queued['id'], array(
					'class_name' => $class['name'],
					'class_time' => $class['start_time'],
				) );
			}
		}
	}

	/**
	 * Mark classes complete once their end_time has passed, and queue a review-request follow-up.
	 */
	private static function auto_complete_classes() {
		global $wpdb;
		$now            = g2a_crm_now();
		$classes_table  = G2A_CRM_Classes_Repository::table();
		$students_table = G2A_CRM_Class_Students_Repository::table();
		$messages_table = g2a_crm_table( 'messages' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$classes_table} SET status = 'completed', updated_at = %s WHERE status IN ('scheduled','in_progress') AND end_time IS NOT NULL AND end_time < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$now
			)
		);

		// Find recently completed classes (last 2 hours) and queue follow-up for attended students.
		$two_hours_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) );
		$classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$classes_table} WHERE status = 'completed' AND updated_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$two_hours_ago,
				$now
			),
			ARRAY_A
		);

		foreach ( $classes ?: array() as $class ) {
			// Flip remaining registered students to attended or no_show — staff can correct manually.
			$students = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, customer_id, status, attended FROM {$students_table} WHERE class_id = %d AND status IN ('registered','confirmed','checked_in')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $class['id']
				),
				ARRAY_A
			);
			foreach ( $students ?: array() as $s ) {
				$new_status = $s['attended'] || 'checked_in' === $s['status'] ? 'attended' : 'no_show';
				$wpdb->update(
					$students_table,
					array( 'status' => $new_status, 'updated_at' => $now ),
					array( 'id' => (int) $s['id'] )
				);
			}

			// Queue review-request for everyone who actually attended.
			$attended = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT customer_id FROM {$students_table} WHERE class_id = %d AND status IN ('attended','completed')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $class['id']
				),
				ARRAY_A
			);
			$followup_template = G2A_CRM_Message_Templates_Repository::find_by_slug( 'class_followup' );
			foreach ( $attended ?: array() as $a ) {
				$queued = G2A_CRM_Messages_Repository::queue( array(
					'customer_id' => (int) $a['customer_id'],
					'channel'     => 'reminder',
					'template_id' => $followup_template ? (int) $followup_template['id'] : null,
					'subject'     => $followup_template ? $followup_template['subject'] : sprintf( __( 'Thanks for taking %s', 'guns2ammo-crm' ), $class['name'] ),
					'body'        => $followup_template ? $followup_template['body']    : sprintf(
						__( "Thanks for joining us for '%s'. We'd love your feedback — reply or stop by next visit.", 'guns2ammo-crm' ),
						$class['name']
					),
					'dedup_key'   => 'class_followup:' . $class['id'] . ':' . $a['customer_id'],
				) );
				if ( ! is_wp_error( $queued ) && ! empty( $queued['id'] ) && $followup_template ) {
					self::pre_substitute_body( (int) $queued['id'], array( 'class_name' => $class['name'] ) );
				}
			}
		}
	}

	private static function queue_renewal_warnings() {
		global $wpdb;
		$warn_days = (int) G2A_CRM_Settings::get( 'renewal_warn_days', 10 );
		if ( $warn_days < 1 ) {
			return;
		}

		$memberships_table = G2A_CRM_Memberships_Repository::table();
		$today             = gmdate( 'Y-m-d' );
		$horizon           = gmdate( 'Y-m-d', strtotime( "+{$warn_days} days" ) );
		$now               = g2a_crm_now();
		$template          = G2A_CRM_Message_Templates_Repository::find_by_slug( 'membership_renewal' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, plan_name, renewal_date, status FROM {$memberships_table} WHERE status IN ('active','trial','past_due') AND renewal_date IS NOT NULL AND renewal_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$today,
				$horizon
			),
			ARRAY_A
		);

		foreach ( $rows ?: array() as $m ) {
			$queued = G2A_CRM_Messages_Repository::queue( array(
				'customer_id' => (int) $m['customer_id'],
				'channel'     => 'reminder',
				'template_id' => $template ? (int) $template['id'] : null,
				'subject'     => $template ? $template['subject'] : sprintf( __( "Your %s membership renews soon", 'guns2ammo-crm' ), $m['plan_name'] ),
				'body'        => $template ? $template['body']    : sprintf(
					__( "Heads up: your '%1\$s' membership renews on %2\$s.", 'guns2ammo-crm' ),
					$m['plan_name'],
					$m['renewal_date']
				),
				'dedup_key'   => 'membership_renewal:' . $m['id'] . ':' . $m['renewal_date'],
			) );
			if ( ! is_wp_error( $queued ) && ! empty( $queued['id'] ) && $template ) {
				self::pre_substitute_body( (int) $queued['id'], array(
					'plan_name'    => $m['plan_name'],
					'renewal_date' => $m['renewal_date'],
				) );
			}

			// If status is past_due, also create a staff follow-up task.
			if ( 'past_due' === $m['status'] ) {
				$wpdb->insert(
					G2A_CRM_Tasks_Repository::table(),
					array(
						'related_type' => 'membership',
						'related_id'   => (int) $m['id'],
						'title'        => sprintf( /* translators: %s: plan name */ __( "Past-due membership: %s — call customer", 'guns2ammo-crm' ), $m['plan_name'] ),
						'priority'     => 'high',
						'status'       => 'open',
						'due_at'       => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
						'created_at'   => $now,
						'updated_at'   => $now,
					)
				);
			}
		}
	}

	private static function queue_waiver_expiry_warnings() {
		global $wpdb;
		$warn_days = (int) G2A_CRM_Settings::get( 'waiver_warn_days', 14 );
		if ( $warn_days < 1 ) {
			return;
		}

		$waivers_table = G2A_CRM_Waivers_Repository::table();
		$threshold     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$warn_days} days" ) );
		$now           = g2a_crm_now();
		$template      = G2A_CRM_Message_Templates_Repository::find_by_slug( 'waiver_expiry' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, expires_at FROM {$waivers_table} WHERE status IN ('submitted','verified') AND expires_at IS NOT NULL AND expires_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$threshold
			),
			ARRAY_A
		);

		foreach ( $rows ?: array() as $waiver ) {
			$queued = G2A_CRM_Messages_Repository::queue( array(
				'customer_id' => (int) $waiver['customer_id'],
				'channel'     => 'reminder',
				'template_id' => $template ? (int) $template['id'] : null,
				'subject'     => $template ? $template['subject'] : __( 'Your range waiver is expiring soon', 'guns2ammo-crm' ),
				'body'        => $template ? $template['body']    : sprintf(
					__( "Your signed range waiver expires on %s. Please stop by the kiosk on your next visit to re-sign.", 'guns2ammo-crm' ),
					$waiver['expires_at']
				),
				'dedup_key'   => 'waiver_expiry:' . $waiver['id'],
			) );
			if ( ! is_wp_error( $queued ) && ! empty( $queued['id'] ) && $template ) {
				self::pre_substitute_body( (int) $queued['id'], array( 'waiver_expires' => $waiver['expires_at'] ) );
			}
		}
	}

	/**
	 * Substitute non-customer variables into a queued message's body/subject
	 * before the dispatcher picks it up. Customer vars (first_name, etc.) are
	 * resolved at dispatch time.
	 *
	 * @param int   $message_id
	 * @param array $vars
	 */
	private static function pre_substitute_body( $message_id, array $vars ) {
		global $wpdb;
		$row = G2A_CRM_Messages_Repository::find( $message_id );
		if ( ! $row ) {
			return;
		}
		$wpdb->update(
			G2A_CRM_Messages_Repository::table(),
			array(
				'subject' => G2A_CRM_Message_Renderer::render( $row['subject'], $vars ),
				'body'    => G2A_CRM_Message_Renderer::render( $row['body'], $vars ),
			),
			array( 'id' => $message_id )
		);
	}

	private static function mark_overdue_tasks() {
		global $wpdb;
		$now    = g2a_crm_now();
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . G2A_CRM_Tasks_Repository::table() . ' SET status = "overdue", updated_at = %s WHERE status IN ("open","in_progress","waiting") AND due_at IS NOT NULL AND due_at < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$now,
				$now
			)
		);
	}
}
