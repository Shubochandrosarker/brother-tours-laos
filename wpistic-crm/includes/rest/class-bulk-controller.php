<?php
/**
 * Bulk-action + saved-view REST controller.
 *
 * - POST /customers/bulk   { action, ids[], params{} }
 * - POST /leads/bulk       { action, ids[], params{} }
 * - GET  /saved-views?resource=X
 * - POST /saved-views      { resource, name, filters{} }
 * - DELETE /saved-views/{id}
 *
 * The bulk endpoints require a base view cap and let the service enforce the
 * specific cap per action. Saved views require only login.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Bulk_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/customers/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'customers_bulk' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'leads_bulk' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_leads' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/saved-views',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_views' ),
					'permission_callback' => $this->require_login(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_view' ),
					'permission_callback' => $this->require_login(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/saved-views/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_view' ),
				'permission_callback' => $this->require_login(),
			)
		);
	}

	private function require_login() {
		return static function () {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
			}
			return true;
		};
	}

	public function customers_bulk( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$action = sanitize_key( (string) ( $body['action'] ?? '' ) );
		$ids    = is_array( $body['ids'] ?? null ) ? $body['ids'] : array();
		$params = is_array( $body['params'] ?? null ) ? $body['params'] : array();

		$result = G2A_CRM_Bulk_Actions::run_customers( $action, $ids, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function leads_bulk( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$action = sanitize_key( (string) ( $body['action'] ?? '' ) );
		$ids    = is_array( $body['ids'] ?? null ) ? $body['ids'] : array();
		$params = is_array( $body['params'] ?? null ) ? $body['params'] : array();

		$result = G2A_CRM_Bulk_Actions::run_leads( $action, $ids, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function list_views( WP_REST_Request $request ) {
		$resource = $request->get_param( 'resource' );
		return $this->ok( G2A_CRM_Saved_Views::list_for_current_user( $resource ) );
	}

	public function create_view( WP_REST_Request $request ) {
		$body   = $request->get_json_params() ?: array();
		$result = G2A_CRM_Saved_Views::create( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function delete_view( WP_REST_Request $request ) {
		$result = G2A_CRM_Saved_Views::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}
}
