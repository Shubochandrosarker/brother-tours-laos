<?php
/**
 * Settings REST controller — /g2a-crm/v1/settings
 *
 * Reads are gated by view_audit_logs so any staff who can see audit data can
 * also see the configuration that produced it. Writes require manage_settings.
 *
 * Sensitive fields (Twilio token in particular) are masked on read so they
 * never leave the server in the clear once configured.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Settings_Controller extends G2A_CRM_REST_Base_Controller {

	const SECRET_KEYS = array( 'sms_twilio_token' );

	const ALLOWED_KEYS = array(
		'business_name',
		'business_email',
		'business_phone',
		'business_timezone',
		'waiver_expiry_days',
		'waiver_warn_days',
		'waiver_required_booking_types',
		'lead_follow_up_hours',
		'booking_reminder_lead_hours',
		'class_reminder_lead_hours',
		'renewal_warn_days',
		'kiosk_enabled',
		'sms_provider',
		'sms_twilio_sid',
		'sms_twilio_token',
		'sms_twilio_from',
		'messaging_test_mode',
		'email_from_name',
		'email_from_address',
		'compliance_review_required_for',
		'restrict_sensitive_view_to_roles',
		'lead_auto_assign_enabled',
		'lead_auto_assign_strategy',
		'lead_auto_assign_pool',
		'lead_auto_assign_routes',
		'lead_match_customer_on_create',
		'lead_create_followup_task',
		'lead_followup_task_priority',
		'lead_notify_assignee',
	);

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_settings' ),
				),
			)
		);
	}

	public function get_items() {
		return $this->ok( $this->mask_secrets( G2A_CRM_Settings::all() ) );
	}

	public function update_items( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		$current = G2A_CRM_Settings::all();
		$patch   = $this->sanitize_patch( $payload, $current );

		if ( empty( $patch ) ) {
			return $this->ok( $this->mask_secrets( $current ) );
		}

		$next = G2A_CRM_Settings::update( $patch );

		$diff = G2A_CRM_Audit_Log::diff(
			$this->redact_secrets_for_log( $current ),
			$this->redact_secrets_for_log( $next )
		);
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'settings.update', 'settings', 0, $diff['old'], $diff['new'] );
		}

		return $this->ok( $this->mask_secrets( $next ) );
	}

	private function sanitize_patch( array $payload, array $current ) {
		$patch = array();
		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $payload ) ) {
				continue;
			}
			$value = $payload[ $key ];

			// Don't overwrite stored secrets with the masked placeholder.
			if ( in_array( $key, self::SECRET_KEYS, true ) && is_string( $value ) && '' !== ( $current[ $key ] ?? '' ) && preg_match( '/^\*+$/', $value ) ) {
				continue;
			}

			$patch[ $key ] = $this->coerce( $key, $value );
		}
		return $patch;
	}

	private function coerce( $key, $value ) {
		switch ( $key ) {
			case 'kiosk_enabled':
			case 'messaging_test_mode':
			case 'lead_auto_assign_enabled':
			case 'lead_match_customer_on_create':
			case 'lead_create_followup_task':
			case 'lead_notify_assignee':
				return (bool) $value;

			case 'lead_auto_assign_strategy':
				$strategy = sanitize_key( (string) $value );
				return in_array( $strategy, array( 'round_robin', 'first_available' ), true ) ? $strategy : 'round_robin';

			case 'lead_followup_task_priority':
				$priority = sanitize_key( (string) $value );
				return in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ? $priority : 'normal';

			case 'lead_auto_assign_pool':
				return $this->sanitize_user_id_list( $value );

			case 'lead_auto_assign_routes':
				return $this->sanitize_routes_map( $value );

			case 'waiver_expiry_days':
			case 'waiver_warn_days':
			case 'lead_follow_up_hours':
			case 'booking_reminder_lead_hours':
			case 'class_reminder_lead_hours':
			case 'renewal_warn_days':
				return max( 0, (int) $value );

			case 'business_email':
			case 'email_from_address':
				return sanitize_email( (string) $value );

			case 'business_phone':
				return g2a_crm_sanitize_phone( (string) $value );

			case 'sms_twilio_from':
				return g2a_crm_sanitize_phone( (string) $value );

			case 'business_timezone':
			case 'sms_provider':
				return sanitize_text_field( (string) $value );

			case 'waiver_required_booking_types':
			case 'compliance_review_required_for':
				return $this->sanitize_string_list( $value, 'sanitize_key' );

			case 'restrict_sensitive_view_to_roles':
				return $this->sanitize_string_list( $value, 'sanitize_key' );

			case 'sms_twilio_sid':
			case 'sms_twilio_token':
				return sanitize_text_field( (string) $value );

			case 'business_name':
			case 'email_from_name':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	private function sanitize_user_id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			$id = (int) $item;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function sanitize_routes_map( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $interest => $user_ids ) {
			$interest = sanitize_key( (string) $interest );
			if ( '' === $interest ) {
				continue;
			}
			$out[ $interest ] = $this->sanitize_user_id_list( $user_ids );
		}
		return $out;
	}

	private function sanitize_string_list( $value, $sanitizer ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			$clean = call_user_func( $sanitizer, (string) $item );
			if ( '' !== $clean ) {
				$out[] = $clean;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private function mask_secrets( array $settings ) {
		foreach ( self::SECRET_KEYS as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$settings[ $key ] = '********';
			}
		}
		return $settings;
	}

	private function redact_secrets_for_log( array $settings ) {
		foreach ( self::SECRET_KEYS as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$settings[ $key ] = '[REDACTED]';
			}
		}
		return $settings;
	}
}
