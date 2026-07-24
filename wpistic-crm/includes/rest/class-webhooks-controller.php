<?php
/**
 * Webhooks REST controller — /g2a-crm/v1/webhooks
 *
 * All operations require `g2a_crm_manage_webhooks`. Secrets are masked on
 * read; sending the masked placeholder back on update preserves the existing
 * secret.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Webhooks_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/webhooks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhooks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhooks/(?P<id>\d+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_test' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhooks/(?P<id>\d+)/deliveries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_deliveries' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhooks/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_events' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/webhooks/dispatch-now',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dispatch_now' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_webhooks' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Webhooks_Repository::list(
			array(
				'page'      => $pg['page'],
				'per_page'  => $pg['per_page'],
				'event'     => (string) $request->get_param( 'event' ),
				'is_active' => $request->get_param( 'is_active' ),
			)
		);
		$result['rows'] = array_map( array( $this, 'mask_secret' ), $result['rows'] );
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Webhooks_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Webhook not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $this->mask_secret( $row ) );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Webhooks_Repository::create( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Return the secret once on create so the operator can copy it. Document this in UI.
		return $this->ok( $result, 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Webhooks_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $this->mask_secret( $result ) );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Webhooks_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function send_test( WP_REST_Request $request ) {
		$result = G2A_CRM_Webhook_Dispatcher::send_test( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function list_deliveries( WP_REST_Request $request ) {
		$rows = G2A_CRM_Webhook_Deliveries_Repository::list_for_webhook(
			(int) $request['id'],
			(int) ( $request->get_param( 'limit' ) ?: 25 )
		);
		return $this->ok( $rows );
	}

	public function list_events() {
		return $this->ok(
			array(
				'events'   => G2A_CRM_Webhooks_Repository::EVENTS,
				'wildcard' => '*',
			)
		);
	}

	public function dispatch_now() {
		G2A_CRM_Webhook_Dispatcher::run_dispatch();
		return $this->ok( array( 'dispatched' => true ) );
	}

	private function mask_secret( $row ) {
		if ( is_array( $row ) && ! empty( $row['secret'] ) ) {
			$row['secret'] = '********';
		}
		return $row;
	}
}
