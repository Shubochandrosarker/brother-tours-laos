<?php
/**
 * Customers REST controller — /g2a-crm/v1/customers
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Customers_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/customers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
					'args'                => array(
						'page'          => array( 'type' => 'integer', 'default' => 1 ),
						'per_page'      => array( 'type' => 'integer', 'default' => 25 ),
						'search'        => array( 'type' => 'string' ),
						'customer_type' => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_customers' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_customers' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_delete_customers' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/timeline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_timeline' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/duplicates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_duplicates' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/merge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'merge' ),
				// Merging deletes the source customer — gate on delete cap.
				'permission_callback' => $this->require_cap( 'g2a_crm_delete_customers' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Customers_Repository::list(
			array(
				'page'          => $pg['page'],
				'per_page'      => $pg['per_page'],
				'search'        => (string) $request->get_param( 'search' ),
				'customer_type' => (string) $request->get_param( 'customer_type' ),
				'status'        => (string) $request->get_param( 'status' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$customer = G2A_CRM_Customers_Repository::find( (int) $request['id'] );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $customer );
	}

	public function create_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Customers_Repository::create( (array) $request->get_json_params() ?: $request->get_params() );
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
		$result = G2A_CRM_Customers_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Customers_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function get_duplicates( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! G2A_CRM_Customers_Repository::find( $id ) ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$include_inactive = (bool) $request->get_param( 'include_inactive' );
		return $this->ok( G2A_CRM_Customers_Repository::find_duplicates( $id, $include_inactive ) );
	}

	public function merge( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		// Determine which way the merge goes. The URL id is the "anchor"; the
		// body specifies whether that anchor is the target (default) or the
		// source via the `target_id` field (anchor merges into target_id).
		$anchor_id = (int) $request['id'];
		$target_id = isset( $payload['target_id'] ) ? (int) $payload['target_id'] : 0;
		$source_id = isset( $payload['source_id'] ) ? (int) $payload['source_id'] : 0;

		if ( ! $target_id && ! $source_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Provide target_id (anchor → target) or source_id (source → anchor).', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		if ( $target_id && ! $source_id ) {
			$source_id = $anchor_id;
		} elseif ( $source_id && ! $target_id ) {
			$target_id = $anchor_id;
		}

		$result = G2A_CRM_Customer_Merger::merge( $source_id, $target_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function get_timeline( WP_REST_Request $request ) {
		$customer_id = (int) $request['id'];
		$customer    = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		$audit = G2A_CRM_Audit_Log::list(
			array(
				'object_type' => 'customer',
				'object_id'   => $customer_id,
				'per_page'    => 50,
			)
		);

		$bookings = G2A_CRM_Bookings_Repository::list(
			array(
				'customer_id' => $customer_id,
				'per_page'    => 25,
			)
		);

		$waivers = G2A_CRM_Waivers_Repository::list(
			array(
				'customer_id' => $customer_id,
				'per_page'    => 10,
			)
		);

		$memberships = G2A_CRM_Memberships_Repository::list(
			array(
				'customer_id' => $customer_id,
				'per_page'    => 10,
			)
		);

		$class_history = G2A_CRM_Class_Students_Repository::for_customer( $customer_id, 25 );

		$messages = G2A_CRM_Messages_Repository::list(
			array(
				'customer_id' => $customer_id,
				'per_page'    => 25,
			)
		);

		$purchases = G2A_CRM_Integrations_Repository::list(
			array(
				'local_type' => 'customer',
				'local_id'   => $customer_id,
				'per_page'   => 25,
			)
		);

		return $this->ok(
			array(
				'customer'      => $customer,
				'events'        => $audit['rows'],
				'bookings'      => $bookings['rows'],
				'waivers'       => $waivers['rows'],
				'memberships'   => $memberships['rows'],
				'class_history' => $class_history,
				'messages'      => $messages['rows'],
				'purchases'     => $purchases['rows'],
			)
		);
	}
}
