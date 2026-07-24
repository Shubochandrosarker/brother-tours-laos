<?php
/**
 * Message templates REST controller — /g2a-crm/v1/message-templates
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Message_Templates_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/message-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_send_email' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upsert' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_templates' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/message-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_templates' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_templates' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/message-templates/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_send_email' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$result = G2A_CRM_Message_Templates_Repository::list(
			array(
				'channel'   => (string) $request->get_param( 'channel' ),
				'is_active' => $request->get_param( 'is_active' ),
				'per_page'  => 100,
			)
		);
		return $this->ok( $result );
	}

	public function upsert( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		$result  = G2A_CRM_Message_Templates_Repository::upsert( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$existing = G2A_CRM_Message_Templates_Repository::find( (int) $request['id'] );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Template not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$payload         = $request->get_json_params() ?: array();
		$payload['slug'] = $existing['slug']; // slug is immutable on update.
		$result          = G2A_CRM_Message_Templates_Repository::upsert( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Message_Templates_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function preview( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		$subject = (string) ( $payload['subject'] ?? '' );
		$body    = (string) ( $payload['body'] ?? '' );

		$customer = null;
		if ( ! empty( $payload['customer_id'] ) ) {
			$customer = G2A_CRM_Customers_Repository::find( (int) $payload['customer_id'] );
		}
		if ( ! $customer ) {
			$customer = array(
				'id'         => 0,
				'first_name' => 'Sample',
				'last_name'  => 'Customer',
				'email'      => 'sample@example.com',
				'phone'      => '+15551234567',
			);
		}

		$ctx = G2A_CRM_Message_Renderer::context_for_customer(
			$customer,
			is_array( $payload['variables'] ?? null ) ? (array) $payload['variables'] : array()
		);

		return $this->ok(
			array(
				'subject' => G2A_CRM_Message_Renderer::render( $subject, $ctx ),
				'body'    => G2A_CRM_Message_Renderer::render( $body, $ctx ),
				'context' => $ctx,
			)
		);
	}
}
