<?php
/**
 * Plugin settings. Backed by wp_options for simple flat config; large structured
 * config can go in the wp_g2a_crm_settings table later.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Settings {

	const OPTION_KEY = 'g2a_crm_settings';

	public static function defaults() {
		return array(
			'business_name'                    => 'WPistic',
			'business_email'                   => '',
			'business_phone'                   => '',
			'business_timezone'                => 'America/New_York',
			'waiver_expiry_days'               => 365,
			'waiver_warn_days'                 => 14,
			'waiver_required_booking_types'    => array( 'range_lane', 'class', 'private_training' ),
			'lead_follow_up_hours'             => 24,
			'booking_reminder_lead_hours'      => 24,
			'class_reminder_lead_hours'        => 24,
			'renewal_warn_days'                => 10,
			'kiosk_enabled'                    => false,
			'sms_provider'                     => 'log_only',
			'sms_twilio_sid'                   => '',
			'sms_twilio_token'                 => '',
			'sms_twilio_from'                  => '',
			'messaging_test_mode'              => false,
			'email_from_name'                  => 'WPistic',
			'email_from_address'               => '',
			'compliance_review_required_for'   => array( 'ffl_transfer' ),
			'restrict_sensitive_view_to_roles' => array( 'administrator', 'crm_owner', 'crm_compliance_officer' ),

			// Lead routing (auto-assignment + follow-up + notification).
			'lead_auto_assign_enabled'         => false,
			'lead_auto_assign_strategy'        => 'round_robin', // 'round_robin' | 'first_available'
			'lead_auto_assign_pool'            => array(), // Array of WP user IDs (global fallback pool).
			'lead_auto_assign_routes'          => array(), // interest_type => array of user IDs.
			'lead_match_customer_on_create'    => true,
			'lead_create_followup_task'        => true,
			'lead_followup_task_priority'     => 'normal',
			'lead_notify_assignee'             => true,

			// Inbound bounce webhook. Set to a long random string to enable the
			// /inbound/bounce endpoint. Provider-agnostic — operators wire SES /
			// Postmark / Mailgun / Twilio webhooks through a relay (Zapier /
			// Make) that posts a normalized payload here with this secret in
			// the X-G2A-CRM-Bounce-Secret header.
			'inbound_bounce_secret'            => '',

			// WooCommerce sync.
			// When true, orders are only auto-linked to a CRM customer if the Woo
			// order has a registered WP user_id whose email matches. Guest-checkout
			// orders (no user_id) are parked as local_type='guest_link' for manual
			// reconcile instead of being trusted on billing_email alone. Mitigates
			// the email-match IDOR (a stranger placing an order under a real
			// customer's email otherwise pollutes that customer's purchase history).
			'woo_require_verified_user_link'   => false,
		);
	}

	public static function install_defaults() {
		$existing = get_option( self::OPTION_KEY );
		if ( false === $existing ) {
			update_option( self::OPTION_KEY, self::defaults() );
			return;
		}
		$merged = array_merge( self::defaults(), is_array( $existing ) ? $existing : array() );
		update_option( self::OPTION_KEY, $merged );
	}

	public static function all() {
		$saved = get_option( self::OPTION_KEY, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function update( array $patch ) {
		$current = self::all();
		$next    = array_merge( $current, $patch );
		update_option( self::OPTION_KEY, $next );
		return $next;
	}
}
