<?php
/**
 * Lead auto-processor.
 *
 * Runs after a lead is created. Performs four steps, each independently
 * toggleable in settings:
 *
 *   1. Customer match  — if the lead has email or phone, find an existing
 *      customer and link via leads.customer_id (settings.lead_match_customer_on_create).
 *   2. Auto-assign     — pick a staff user from the per-interest route map
 *      with global pool fallback. Round-robin keeps assignments fair.
 *   3. Follow-up task  — create a task assigned to the chosen staff member,
 *      due settings.lead_follow_up_hours from now.
 *   4. Notify assignee — wp_mail() the staff member that a new lead landed.
 *
 * Hooked from G2A_CRM_Leads_Repository::create() directly (not via the
 * audit-log action) so the controller can return a row that already reflects
 * the assignment. The resulting `lead.update` audit event still fans out
 * through the webhook dispatcher.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Lead_Auto_Assigner {

	const ROUND_ROBIN_OPTION_PREFIX = 'g2a_crm_lead_rr_';

	/**
	 * Run all enabled post-create steps for a freshly-created lead and return
	 * the latest row.
	 *
	 * @param array $lead The just-created lead row.
	 * @return array Refreshed lead row (may have customer_id / assigned_to filled in).
	 */
	public static function process_new_lead( array $lead ) {
		$id = (int) $lead['id'];

		$patch = array();

		if ( G2A_CRM_Settings::get( 'lead_match_customer_on_create', true ) && empty( $lead['customer_id'] ) ) {
			$customer_id = self::find_matching_customer_id( $lead );
			if ( $customer_id ) {
				$patch['customer_id'] = $customer_id;
			}
		}

		if ( G2A_CRM_Settings::get( 'lead_auto_assign_enabled', false ) && empty( $lead['assigned_to'] ) ) {
			$assignee = self::pick_assignee( $lead['interest_type'] ?? 'general' );
			if ( $assignee ) {
				$patch['assigned_to'] = $assignee;
			}
		}

		if ( ! empty( $patch ) ) {
			// Use the repository update so the audit log + activity timeline
			// reflect what happened.
			$updated = G2A_CRM_Leads_Repository::update( $id, $patch );
			if ( ! is_wp_error( $updated ) ) {
				$lead = $updated;
				G2A_CRM_Leads_Repository::activity(
					$id,
					'auto_processed',
					self::describe_patch( $patch )
				);
			}
		}

		// Follow-up task + notification — only when we have an assignee.
		if ( ! empty( $lead['assigned_to'] ) ) {
			if ( G2A_CRM_Settings::get( 'lead_create_followup_task', true ) ) {
				self::create_followup_task( $lead );
			}
			if ( G2A_CRM_Settings::get( 'lead_notify_assignee', true ) ) {
				self::notify_assignee( $lead );
			}
		}

		return $lead;
	}

	private static function find_matching_customer_id( array $lead ) {
		$email = trim( (string) ( $lead['email'] ?? '' ) );
		if ( $email && method_exists( 'G2A_CRM_Customers_Repository', 'find_by_email' ) ) {
			$customer = G2A_CRM_Customers_Repository::find_by_email( $email );
			if ( $customer ) {
				return (int) $customer['id'];
			}
		}
		$phone = g2a_crm_sanitize_phone( (string) ( $lead['phone'] ?? '' ) );
		if ( $phone ) {
			global $wpdb;
			$row = $wpdb->get_var(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT id FROM ' . G2A_CRM_Customers_Repository::table() . ' WHERE phone = %s LIMIT 1',
					$phone
				)
			);
			if ( $row ) {
				return (int) $row;
			}
		}
		return 0;
	}

	/**
	 * Pick the next assignee for an interest type using the configured
	 * strategy. Returns 0 when no pool is available.
	 */
	public static function pick_assignee( $interest_type ) {
		$routes    = (array) G2A_CRM_Settings::get( 'lead_auto_assign_routes', array() );
		$global    = (array) G2A_CRM_Settings::get( 'lead_auto_assign_pool', array() );
		$strategy  = (string) G2A_CRM_Settings::get( 'lead_auto_assign_strategy', 'round_robin' );

		$pool_key = '';
		$pool     = array();

		if ( isset( $routes[ $interest_type ] ) && ! empty( $routes[ $interest_type ] ) ) {
			$pool     = array_map( 'intval', $routes[ $interest_type ] );
			$pool_key = $interest_type;
		} elseif ( ! empty( $global ) ) {
			$pool     = array_map( 'intval', $global );
			$pool_key = '__global';
		}

		$pool = array_values( array_filter( $pool, static function ( $uid ) {
			$user = get_userdata( (int) $uid );
			return $user && user_can( $user, 'g2a_crm_view_leads' );
		} ) );

		if ( empty( $pool ) ) {
			return 0;
		}

		if ( 'first_available' === $strategy ) {
			return (int) $pool[0];
		}

		// Round-robin: cycle through the pool, persisting the cursor per pool key.
		return self::round_robin_pick( $pool_key, $pool );
	}

	private static function round_robin_pick( $pool_key, array $pool ) {
		$option = self::ROUND_ROBIN_OPTION_PREFIX . sanitize_key( $pool_key );
		$cursor = (int) get_option( $option, 0 );
		$choice = (int) $pool[ $cursor % count( $pool ) ];
		update_option( $option, $cursor + 1, false );
		return $choice;
	}

	private static function describe_patch( array $patch ) {
		$bits = array();
		if ( isset( $patch['customer_id'] ) ) {
			$bits[] = sprintf( 'matched to customer #%d', (int) $patch['customer_id'] );
		}
		if ( isset( $patch['assigned_to'] ) ) {
			$bits[] = sprintf( 'auto-assigned to user #%d', (int) $patch['assigned_to'] );
		}
		return $bits ? implode( ', ', $bits ) : 'auto-processed';
	}

	private static function create_followup_task( array $lead ) {
		$hours    = max( 1, (int) G2A_CRM_Settings::get( 'lead_follow_up_hours', 24 ) );
		$priority = (string) G2A_CRM_Settings::get( 'lead_followup_task_priority', 'normal' );

		$display = trim( $lead['name'] ?? '' ) ?: sprintf( 'Lead #%d', (int) $lead['id'] );
		$title   = sprintf(
			/* translators: %s: lead display name */
			__( 'Follow up with new lead: %s', 'guns2ammo-crm' ),
			$display
		);

		$desc_lines = array();
		if ( ! empty( $lead['email'] ) ) {
			$desc_lines[] = 'Email: ' . $lead['email'];
		}
		if ( ! empty( $lead['phone'] ) ) {
			$desc_lines[] = 'Phone: ' . $lead['phone'];
		}
		if ( ! empty( $lead['interest_type'] ) ) {
			$desc_lines[] = 'Interest: ' . $lead['interest_type'];
		}
		if ( ! empty( $lead['source'] ) ) {
			$desc_lines[] = 'Source: ' . $lead['source'];
		}

		G2A_CRM_Tasks_Repository::create(
			array(
				'title'        => $title,
				'description'  => implode( "\n", $desc_lines ),
				'related_type' => 'lead',
				'related_id'   => (int) $lead['id'],
				'assigned_to'  => (int) $lead['assigned_to'],
				'priority'     => $priority,
				'status'       => 'open',
				'due_at'       => gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ),
			)
		);
	}

	private static function notify_assignee( array $lead ) {
		$user = get_userdata( (int) $lead['assigned_to'] );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$site    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$subject = sprintf(
			/* translators: 1: site host, 2: lead display name */
			__( '[%1$s] New lead assigned: %2$s', 'guns2ammo-crm' ),
			$site,
			trim( $lead['name'] ?? '' ) ?: sprintf( 'Lead #%d', (int) $lead['id'] )
		);

		$dashboard = admin_url( 'admin.php?page=g2a-crm#/leads/' . (int) $lead['id'] );

		$lines = array(
			sprintf( __( 'Hi %s,', 'guns2ammo-crm' ), $user->display_name ?: $user->user_login ),
			'',
			__( 'A new lead has been assigned to you in the CRM.', 'guns2ammo-crm' ),
			'',
			'• ' . __( 'Name', 'guns2ammo-crm' ) . ': ' . ( $lead['name'] ?? '' ),
		);
		if ( ! empty( $lead['email'] ) ) {
			$lines[] = '• ' . __( 'Email', 'guns2ammo-crm' ) . ': ' . $lead['email'];
		}
		if ( ! empty( $lead['phone'] ) ) {
			$lines[] = '• ' . __( 'Phone', 'guns2ammo-crm' ) . ': ' . $lead['phone'];
		}
		if ( ! empty( $lead['interest_type'] ) ) {
			$lines[] = '• ' . __( 'Interest', 'guns2ammo-crm' ) . ': ' . $lead['interest_type'];
		}
		if ( ! empty( $lead['source'] ) ) {
			$lines[] = '• ' . __( 'Source', 'guns2ammo-crm' ) . ': ' . $lead['source'];
		}
		$hours   = (int) G2A_CRM_Settings::get( 'lead_follow_up_hours', 24 );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %d: hours */
			__( 'Follow-up due within %d hours.', 'guns2ammo-crm' ),
			$hours
		);
		$lines[] = '';
		$lines[] = __( 'Open lead:', 'guns2ammo-crm' ) . ' ' . $dashboard;

		$from_name    = (string) G2A_CRM_Settings::get( 'email_from_name', 'WPistic' );
		$from_address = (string) G2A_CRM_Settings::get( 'email_from_address', '' );
		$headers      = array();
		if ( $from_address ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		}

		// Test mode short-circuits any actual sending across the plugin.
		if ( G2A_CRM_Settings::get( 'messaging_test_mode', false ) ) {
			return;
		}

		wp_mail( $user->user_email, $subject, implode( "\n", $lines ), $headers );
	}
}
