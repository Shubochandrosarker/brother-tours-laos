<?php
/**
 * Bookings REST controller — /g2a-crm/v1/bookings
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Bookings_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/bookings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_bookings' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_bookings' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'calendar' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_bookings' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_bookings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_bookings' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_bookings' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/check-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_in' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_bookings' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_cancel_bookings' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bookings/(?P<id>\d+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_bookings' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Bookings_Repository::list(
			array(
				'page'         => $pg['page'],
				'per_page'     => $pg['per_page'],
				'customer_id'  => (int) $request->get_param( 'customer_id' ),
				'status'       => (string) $request->get_param( 'status' ),
				'booking_type' => (string) $request->get_param( 'booking_type' ),
				'from'         => (string) $request->get_param( 'from' ),
				'to'           => (string) $request->get_param( 'to' ),
				'today'        => (bool) $request->get_param( 'today' ),
			)
		);

		// Hydrate with minimal customer info for list views.
		$customer_ids = array_unique( array_map( static fn( $r ) => (int) $r['customer_id'], $result['rows'] ) );
		$customers    = $this->fetch_customers_lite( $customer_ids );
		foreach ( $result['rows'] as &$row ) {
			$row['customer'] = $customers[ (int) $row['customer_id'] ] ?? null;
		}

		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function calendar( WP_REST_Request $request ) {
		$from = (string) $request->get_param( 'from' ) ?: gmdate( 'Y-m-d 00:00:00' );
		$to   = (string) $request->get_param( 'to' ) ?: gmdate( 'Y-m-d 23:59:59', strtotime( '+31 days' ) );

		$rows         = G2A_CRM_Bookings_Repository::in_range( $from, $to );
		$customer_ids = array_unique( array_map( static fn( $r ) => (int) $r['customer_id'], $rows ) );
		$customers    = $this->fetch_customers_lite( $customer_ids );

		foreach ( $rows as &$row ) {
			$row['customer'] = $customers[ (int) $row['customer_id'] ] ?? null;
		}

		return $this->ok(
			array(
				'from'     => $from,
				'to'       => $to,
				'bookings' => $rows,
			)
		);
	}

	public function get_item( WP_REST_Request $request ) {
		$booking = G2A_CRM_Bookings_Repository::find( (int) $request['id'] );
		if ( ! $booking ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$booking['customer'] = G2A_CRM_Customers_Repository::find( (int) $booking['customer_id'] );
		return $this->ok( $booking );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Bookings_Repository::create( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Bookings_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Bookings_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function check_in( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$opts    = array(
			'skip_waiver_check' => ! empty( $payload['skip_waiver_check'] ) && current_user_can( 'g2a_crm_verify_waivers' ),
		);
		$result = G2A_CRM_Bookings_Repository::check_in( (int) $request['id'], $opts );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function cancel( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$reason  = is_array( $payload ) ? (string) ( $payload['reason'] ?? '' ) : '';
		$result  = G2A_CRM_Bookings_Repository::cancel( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function complete( WP_REST_Request $request ) {
		$result = G2A_CRM_Bookings_Repository::complete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	private function fetch_customers_lite( array $ids ) {
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT id, first_name, last_name, email, phone, waiver_status FROM ' . G2A_CRM_Customers_Repository::table() . ' WHERE id IN (' . $placeholders . ')',
				$ids
			),
			ARRAY_A
		);
		$out = array();
		foreach ( $rows ?: array() as $row ) {
			$out[ (int) $row['id'] ] = $row;
		}
		return $out;
	}
}
