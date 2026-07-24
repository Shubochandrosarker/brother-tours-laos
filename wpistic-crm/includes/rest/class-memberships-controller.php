<?php
/**
 * Memberships REST controller — /g2a-crm/v1/memberships
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Memberships_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/memberships',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_memberships' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memberships/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_memberships' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memberships/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memberships/(?P<id>\d+)/renew',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'renew' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memberships/(?P<id>\d+)/suspend',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'suspend' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_memberships' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Memberships_Repository::list(
			array(
				'page'               => $pg['page'],
				'per_page'           => $pg['per_page'],
				'customer_id'        => (int) $request->get_param( 'customer_id' ),
				'status'             => (string) $request->get_param( 'status' ),
				'billing_status'     => (string) $request->get_param( 'billing_status' ),
				'renews_within_days' => (int) $request->get_param( 'renews_within_days' ),
			)
		);

		// Hydrate customer lite info for the list view.
		$customer_ids = array_unique( array_map( static fn( $r ) => (int) $r['customer_id'], $result['rows'] ) );
		if ( $customer_ids ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );
			$customers    = $wpdb->get_results(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT id, first_name, last_name, email, phone FROM ' . G2A_CRM_Customers_Repository::table() . ' WHERE id IN (' . $placeholders . ')',
					$customer_ids
				),
				ARRAY_A
			);
			$by_id = array();
			foreach ( $customers ?: array() as $c ) {
				$by_id[ (int) $c['id'] ] = $c;
			}
			foreach ( $result['rows'] as &$row ) {
				$row['customer'] = $by_id[ (int) $row['customer_id'] ] ?? null;
			}
		}

		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Memberships_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$row['customer'] = G2A_CRM_Customers_Repository::find( (int) $row['customer_id'] );
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Memberships_Repository::create( $payload );
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
		$result = G2A_CRM_Memberships_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Memberships_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function cancel( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$reason  = is_array( $payload ) ? (string) ( $payload['reason'] ?? '' ) : '';
		$result  = G2A_CRM_Memberships_Repository::cancel( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function renew( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$months  = is_array( $payload ) ? (int) ( $payload['months'] ?? 1 ) : 1;
		$result  = G2A_CRM_Memberships_Repository::renew( (int) $request['id'], $months );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function suspend( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$reason  = is_array( $payload ) ? (string) ( $payload['reason'] ?? '' ) : '';
		$result  = G2A_CRM_Memberships_Repository::suspend( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}
}
