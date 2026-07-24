<?php
/**
 * Suppressions REST controller.
 *
 * Authenticated endpoints (require g2a_crm_manage_settings):
 *   GET    /suppressions
 *   POST   /suppressions          { channel, address, reason? }
 *   DELETE /suppressions/{id}
 *
 * Public-but-authenticated-via-shared-secret endpoint:
 *   POST   /inbound/bounce
 *     Header: X-G2A-CRM-Bounce-Secret: <hex secret from settings>
 *     Body:   { channel: "email"|"sms", address: "...", type:
 *              "hard_bounce"|"soft_bounce"|"complaint"|"unsubscribe",
 *              reason?: "..." }
 *
 * The inbound endpoint is provider-agnostic by design — operators wire SES /
 * Postmark / Mailgun / Twilio webhooks through Zapier/Make and normalize the
 * payload before forwarding here. That keeps provider-specific signature
 * verification (and the API churn it brings) out of the plugin.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Suppressions_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/suppressions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_settings' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/suppressions/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_settings' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inbound/bounce',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inbound_bounce' ),
				'permission_callback' => array( $this, 'verify_bounce_secret' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Suppressions_Repository::list(
			array(
				'page'     => $pg['page'],
				'per_page' => $pg['per_page'],
				'channel'  => (string) $request->get_param( 'channel' ),
				'source'   => (string) $request->get_param( 'source' ),
				'search'   => (string) $request->get_param( 'search' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function create_item( WP_REST_Request $request ) {
		$body   = $request->get_json_params() ?: array();
		$result = G2A_CRM_Suppressions_Repository::add(
			(string) ( $body['channel'] ?? '' ),
			(string) ( $body['address'] ?? '' ),
			(string) ( $body['reason'] ?? '' ),
			'manual'
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Suppressions_Repository::remove( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	/**
	 * Permission callback for /inbound/bounce. Uses a shared secret stored
	 * in settings ('inbound_bounce_secret'). Constant-time compare via
	 * hash_equals.
	 */
	public function verify_bounce_secret( WP_REST_Request $request ) {
		$expected = (string) G2A_CRM_Settings::get( 'inbound_bounce_secret', '' );
		if ( '' === $expected ) {
			return new WP_Error( 'g2a_crm_disabled', __( 'Inbound bounce endpoint is disabled. Set inbound_bounce_secret in CRM Settings to enable.', 'guns2ammo-crm' ), array( 'status' => 503 ) );
		}
		$provided = (string) $request->get_header( 'X-G2A-CRM-Bounce-Secret' );
		if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'Invalid bounce secret.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function inbound_bounce( WP_REST_Request $request ) {
		$body    = $request->get_json_params() ?: array();
		$channel = sanitize_key( (string) ( $body['channel'] ?? '' ) );
		$address = (string) ( $body['address'] ?? '' );
		$type    = sanitize_key( (string) ( $body['type'] ?? 'hard_bounce' ) );
		$reason  = sanitize_text_field( (string) ( $body['reason'] ?? '' ) );

		if ( ! in_array( $channel, array( 'email', 'sms' ), true ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'channel must be email or sms.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( '' === $address ) {
			return new WP_Error( 'g2a_crm_validation', __( 'address is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		// Soft bounces are recorded for observability but don't suppress —
		// transient failures (mailbox full, server down) often resolve.
		if ( 'soft_bounce' === $type ) {
			G2A_CRM_Audit_Log::record(
				'suppression.soft_bounce',
				'suppression',
				0,
				null,
				array( 'channel' => $channel, 'reason' => $reason )
			);
			return $this->ok( array( 'recorded' => true, 'suppressed' => false ) );
		}

		$source = in_array( $type, array( 'hard_bounce', 'complaint', 'unsubscribe' ), true ) ? $type : 'hard_bounce';
		$result = G2A_CRM_Suppressions_Repository::add( $channel, $address, $reason, $source );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'recorded' => true, 'suppressed' => true, 'id' => (int) $result['id'] ) );
	}
}
