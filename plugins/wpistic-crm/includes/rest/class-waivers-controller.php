<?php
/**
 * Waivers REST controller — /g2a-crm/v1/waivers
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Waivers_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/waivers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_waivers' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_waivers' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/waivers/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_waivers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/waivers/(?P<id>\d+)/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_verify_waivers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/waivers/(?P<id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_verify_waivers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/check-in/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_in_lookup' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_bookings' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Waivers_Repository::list(
			array(
				'page'                 => $pg['page'],
				'per_page'             => $pg['per_page'],
				'customer_id'          => (int) $request->get_param( 'customer_id' ),
				'status'               => (string) $request->get_param( 'status' ),
				'needs_verification'   => (bool) $request->get_param( 'needs_verification' ),
				'expiring_within_days' => (int) $request->get_param( 'expiring_within_days' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$waiver = G2A_CRM_Waivers_Repository::find( (int) $request['id'] );
		if ( ! $waiver ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Waiver not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$waiver['customer'] = G2A_CRM_Customers_Repository::find( (int) $waiver['customer_id'] );
		return $this->ok( $waiver );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		$signed  = isset( $payload['signed_data'] ) && is_array( $payload['signed_data'] ) ? $payload['signed_data'] : array();
		unset( $payload['signed_data'] );

		$result = G2A_CRM_Waivers_Repository::create( $payload, $signed, 'staff' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function verify( WP_REST_Request $request ) {
		$result = G2A_CRM_Waivers_Repository::verify( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function reject( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$reason  = is_array( $payload ) ? (string) ( $payload['reason'] ?? '' ) : '';
		$result  = G2A_CRM_Waivers_Repository::reject( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	/**
	 * Mission Check-In: search by name/email/phone, return customer + waiver + upcoming bookings in one call.
	 */
	public function check_in_lookup( WP_REST_Request $request ) {
		$search = trim( (string) $request->get_param( 'search' ) );
		if ( '' === $search ) {
			return $this->ok( array( 'customers' => array() ) );
		}

		$result = G2A_CRM_Customers_Repository::list(
			array(
				'search'   => $search,
				'per_page' => 10,
			)
		);

		$out = array();
		foreach ( $result['rows'] as $customer ) {
			$bookings = G2A_CRM_Bookings_Repository::list(
				array(
					'customer_id' => (int) $customer['id'],
					'from'        => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
					'to'          => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
					'per_page'    => 5,
				)
			);
			$out[] = array(
				'customer'         => $customer,
				'has_valid_waiver' => G2A_CRM_Waivers_Repository::has_valid_waiver( (int) $customer['id'] ),
				'upcoming'         => $bookings['rows'],
			);
		}

		return $this->ok( array( 'customers' => $out ) );
	}
}
