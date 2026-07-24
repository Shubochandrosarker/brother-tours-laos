<?php
/**
 * Tags REST controller.
 *
 *   /g2a-crm/v1/tags                     list/create
 *   /g2a-crm/v1/tags/{id}                read/update/delete
 *   /g2a-crm/v1/customers/{id}/tags      list/attach/detach for a customer
 *
 * Tag taxonomy edits require g2a_crm_manage_tags. Reading tags is allowed
 * for anyone who can view customers (so chips can render anywhere a customer
 * card appears). Attaching/detaching tags to a customer follows the customer
 * edit capability.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Tags_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_tags' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tags/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_tags' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_tags' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_for_customer' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'attach' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_customers' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/tags/(?P<tag_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'detach' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_customers' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Tags_Repository::list(
			array(
				'page'     => $pg['page'],
				'per_page' => $pg['per_page'],
				'search'   => (string) $request->get_param( 'search' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Tags_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Tag not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Tags_Repository::create( $payload );
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
		$result = G2A_CRM_Tags_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Tags_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function list_for_customer( WP_REST_Request $request ) {
		$customer_id = (int) $request['id'];
		$customer    = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( G2A_CRM_Tags_Repository::for_customer( $customer_id ) );
	}

	public function attach( WP_REST_Request $request ) {
		$customer_id = (int) $request['id'];
		$payload     = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$tag_id = isset( $payload['tag_id'] ) ? (int) $payload['tag_id'] : 0;
		$slug   = isset( $payload['slug'] ) ? sanitize_key( $payload['slug'] ) : '';

		if ( ! $tag_id && $slug ) {
			$existing = G2A_CRM_Tags_Repository::find_by_slug( $slug );
			if ( ! $existing && ! empty( $payload['create_if_missing'] ) ) {
				if ( ! current_user_can( 'g2a_crm_manage_tags' ) ) {
					return new WP_Error( 'g2a_crm_forbidden', __( 'You cannot create new tags.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
				}
				$created = G2A_CRM_Tags_Repository::create(
					array(
						'name'  => isset( $payload['name'] ) ? $payload['name'] : $slug,
						'slug'  => $slug,
						'color' => isset( $payload['color'] ) ? $payload['color'] : '',
					)
				);
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				$tag_id = (int) $created['id'];
			} elseif ( $existing ) {
				$tag_id = (int) $existing['id'];
			}
		}

		if ( ! $tag_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Provide tag_id or slug.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$result = G2A_CRM_Tags_Repository::attach_to_customer( $customer_id, $tag_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function detach( WP_REST_Request $request ) {
		$customer_id = (int) $request['id'];
		$tag_id      = (int) $request['tag_id'];
		$result      = G2A_CRM_Tags_Repository::detach_from_customer( $customer_id, $tag_id );
		return $this->ok( $result );
	}
}
