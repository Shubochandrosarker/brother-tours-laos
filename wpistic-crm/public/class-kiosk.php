<?php
/**
 * Public kiosk endpoint — customer-facing waiver flow.
 *
 * Accessed via ?g2a_kiosk=1 on the home URL or any front-end page. No login
 * required. Submissions hit /wp-json/g2a-crm/v1/kiosk/* which uses a kiosk
 * nonce instead of the logged-in REST nonce.
 *
 * Rate limits and disable-via-settings keep this from being abused.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Kiosk {

	const QUERY_VAR     = 'g2a_kiosk';
	const NONCE_ACTION  = 'g2a_crm_kiosk';
	const RATE_LIMIT_KEY_PREFIX = 'g2a_crm_kiosk_rl_';
	const RATE_LIMIT_MAX        = 30;   // requests per window
	const RATE_LIMIT_WINDOW_SEC = 600;  // 10 minutes

	/**
	 * Check-in tokens bind the resolved customer_id from step 1 to the waiver
	 * submission in step 2. Lifetime is short (15 min) — the customer is
	 * expected to sign at the front desk in one continuous session.
	 */
	const CHECKIN_TOKEN_TTL_SEC = 900;

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_query_var() {
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
	}

	public static function maybe_render() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! G2A_CRM_Settings::get( 'kiosk_enabled', false ) ) {
			status_header( 403 );
			exit( 'Kiosk is disabled. Enable it in CRM Settings.' );
		}

		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		include G2A_CRM_PLUGIN_DIR . 'public/views/kiosk.php';
		exit;
	}

	public static function register_routes() {
		register_rest_route(
			G2A_CRM_REST_NAMESPACE,
			'/kiosk/check-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'check_in_handler' ),
				'permission_callback' => array( __CLASS__, 'verify_request' ),
			)
		);

		register_rest_route(
			G2A_CRM_REST_NAMESPACE,
			'/kiosk/waiver',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'waiver_handler' ),
				'permission_callback' => array( __CLASS__, 'verify_request' ),
			)
		);
	}

	public static function verify_request( WP_REST_Request $request ) {
		if ( ! G2A_CRM_Settings::get( 'kiosk_enabled', false ) ) {
			return new WP_Error( 'g2a_crm_kiosk_disabled', __( 'Kiosk is disabled.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		$nonce = $request->get_header( 'X-G2A-Kiosk-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'g2a_crm_kiosk_nonce', __( 'Invalid kiosk session.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		if ( ! self::within_rate_limit() ) {
			return new WP_Error( 'g2a_crm_kiosk_rate', __( 'Too many requests. Please wait a moment.', 'guns2ammo-crm' ), array( 'status' => 429 ) );
		}
		return true;
	}

	private static function within_rate_limit() {
		$key   = self::RATE_LIMIT_KEY_PREFIX . md5( g2a_crm_request_ip() ?: 'unknown' );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW_SEC );
		return true;
	}

	/**
	 * Step 1: customer arrives, types phone/email. We find or create a customer profile.
	 */
	public static function check_in_handler( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();

		$email = isset( $payload['email'] ) ? sanitize_email( (string) $payload['email'] ) : '';
		$phone = isset( $payload['phone'] ) ? g2a_crm_sanitize_phone( (string) $payload['phone'] ) : '';
		$first = isset( $payload['first_name'] ) ? sanitize_text_field( (string) $payload['first_name'] ) : '';
		$last  = isset( $payload['last_name'] ) ? sanitize_text_field( (string) $payload['last_name'] ) : '';

		if ( '' === $email && '' === $phone ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Email or phone is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$customer = null;
		if ( $email ) {
			$customer = G2A_CRM_Customers_Repository::find_by_email( $email );
		}
		if ( ! $customer && $phone ) {
			$customer = self::find_by_phone( $phone );
		}

		if ( ! $customer ) {
			if ( '' === $first ) {
				// First-time kiosk arrival without prior data — accept minimal fields.
				return new WP_Error(
					'g2a_crm_kiosk_new_customer',
					__( 'No existing profile. Please provide your first and last name.', 'guns2ammo-crm' ),
					array( 'status' => 200, 'needs_profile' => true )
				);
			}
			$customer = G2A_CRM_Customers_Repository::create(
				array(
					'first_name'    => $first,
					'last_name'     => $last,
					'email'         => $email,
					'phone'         => $phone,
					'customer_type' => 'walk_in',
					'source'        => 'kiosk',
				)
			);
			if ( is_wp_error( $customer ) ) {
				return $customer;
			}
		}

		$customer_id  = (int) $customer['id'];
		$session_data = self::issue_checkin_token( $customer_id );

		return new WP_REST_Response(
			array(
				'customer'         => array(
					'id'         => $customer_id,
					'first_name' => $customer['first_name'],
					'last_name'  => $customer['last_name'],
				),
				'has_valid_waiver' => G2A_CRM_Waivers_Repository::has_valid_waiver( $customer_id ),
				'checkin_token'    => $session_data['token'],
				'expires_at'       => $session_data['expires_at'],
			)
		);
	}

	/**
	 * Step 2: customer signs the waiver. The check-in token from step 1 binds
	 * the waiver to the customer identified by email/phone — without it any
	 * client could submit a waiver for an arbitrary customer_id (IDOR).
	 */
	public static function waiver_handler( WP_REST_Request $request ) {
		$payload     = $request->get_json_params() ?: array();
		$customer_id = (int) ( $payload['customer_id'] ?? 0 );
		$token       = isset( $payload['checkin_token'] ) ? (string) $payload['checkin_token'] : '';
		$waiver_type = sanitize_key( $payload['waiver_type'] ?? 'range' );
		$signed_data = array(
			'typed_name'      => (string) ( $payload['typed_name'] ?? '' ),
			'signature_image' => (string) ( $payload['signature_image'] ?? '' ),
		);

		if ( ! $customer_id || ! self::verify_checkin_token( $customer_id, $token ) ) {
			return new WP_Error(
				'g2a_crm_kiosk_session',
				__( 'Invalid or expired kiosk session. Please re-enter your contact info to start over.', 'guns2ammo-crm' ),
				array( 'status' => 403 )
			);
		}

		$result = G2A_CRM_Waivers_Repository::create(
			array(
				'customer_id' => $customer_id,
				'waiver_type' => $waiver_type,
			),
			$signed_data,
			'kiosk'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'waiver_id'  => (int) $result['id'],
				'expires_at' => $result['expires_at'],
				'pdf_url'    => $result['pdf_url'],
			),
			201
		);
	}

	/**
	 * Issue a short-lived signed token binding the resolved customer to this
	 * kiosk session. Recipients must echo it on the waiver POST.
	 *
	 * Format: <expires_unix>.<hmac(customer_id|expires_unix)>
	 *
	 * @param int $customer_id
	 * @return array{token:string,expires_at:string}
	 */
	private static function issue_checkin_token( $customer_id ) {
		$expires = time() + self::CHECKIN_TOKEN_TTL_SEC;
		$mac     = self::sign_checkin( (int) $customer_id, $expires );
		return array(
			'token'      => $expires . '.' . $mac,
			'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
		);
	}

	private static function verify_checkin_token( $customer_id, $token ) {
		if ( '' === $token || false === strpos( $token, '.' ) ) {
			return false;
		}
		list( $expires, $mac ) = explode( '.', $token, 2 );
		$expires = (int) $expires;
		if ( $expires < time() ) {
			return false;
		}
		$expected = self::sign_checkin( (int) $customer_id, $expires );
		return hash_equals( $expected, (string) $mac );
	}

	private static function sign_checkin( $customer_id, $expires ) {
		return wp_hash( sprintf( 'g2a_kiosk_checkin:%d:%d', (int) $customer_id, (int) $expires ) );
	}

	private static function find_by_phone( $phone ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . G2A_CRM_Customers_Repository::table() . ' WHERE phone = %s ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$phone
			),
			ARRAY_A
		);
		return $row ?: null;
	}
}
