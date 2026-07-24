<?php
/**
 * Messages REST controller — /g2a-crm/v1/messages
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Messages_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => array( $this, 'permission_send_email_or_sms' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/(?P<id>\d+)/retry',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retry' ),
				'permission_callback' => array( $this, 'permission_send_email_or_sms' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/send-email',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_email' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_send_email' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/send-sms',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_sms' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_send_sms' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages/dispatch-now',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dispatch_now' ),
				'permission_callback' => array( $this, 'permission_send_email_or_sms' ),
			)
		);
	}

	public function permission_send_email_or_sms() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		if ( current_user_can( 'g2a_crm_send_email' ) || current_user_can( 'g2a_crm_send_sms' ) ) {
			return true;
		}
		return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to manage messages.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Messages_Repository::list(
			array(
				'page'        => $pg['page'],
				'per_page'    => $pg['per_page'],
				'customer_id' => (int) $request->get_param( 'customer_id' ),
				'channel'     => (string) $request->get_param( 'channel' ),
				'status'      => (string) $request->get_param( 'status' ),
				'search'      => (string) $request->get_param( 'search' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Messages_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Message not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $row['customer_id'] ) ) {
			$row['customer'] = G2A_CRM_Customers_Repository::find( (int) $row['customer_id'] );
		}
		return $this->ok( $row );
	}

	public function send_email( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		return $this->queue_and_optionally_send( $payload, 'email' );
	}

	public function send_sms( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		return $this->queue_and_optionally_send( $payload, 'sms' );
	}

	private function queue_and_optionally_send( array $payload, $channel ) {
		$customer_id = (int) ( $payload['customer_id'] ?? 0 );
		if ( ! $customer_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'customer_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$customer = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		$subject = (string) ( $payload['subject'] ?? '' );
		$body    = (string) ( $payload['body'] ?? '' );

		// Allow staff to pick a template by slug.
		if ( ! empty( $payload['template_slug'] ) ) {
			$tpl = G2A_CRM_Message_Templates_Repository::find_by_slug( (string) $payload['template_slug'] );
			if ( $tpl ) {
				$subject = '' !== $subject ? $subject : $tpl['subject'];
				$body    = '' !== $body    ? $body    : $tpl['body'];
			}
		}

		if ( '' === trim( $body ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'body is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( 'email' === $channel && '' === trim( $subject ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'subject is required for email.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$queued = G2A_CRM_Messages_Repository::queue( array(
			'customer_id' => $customer_id,
			'channel'     => $channel,
			'subject'     => $subject,
			'body'        => $body,
			'to_address'  => 'sms' === $channel ? $customer['phone'] : $customer['email'],
		) );
		if ( is_wp_error( $queued ) ) {
			return $queued;
		}

		if ( ! empty( $payload['send_now'] ) ) {
			// Single-row dispatch — claim only this row.
			global $wpdb;
			$wpdb->update(
				G2A_CRM_Messages_Repository::table(),
				array( 'status' => 'sending', 'attempt_count' => 1 ),
				array( 'id' => (int) $queued['id'], 'status' => 'queued' )
			);
			$fresh = G2A_CRM_Messages_Repository::find( (int) $queued['id'] );
			if ( $fresh && 'sending' === $fresh['status'] ) {
				G2A_CRM_Message_Dispatcher::dispatch_one( $fresh );
			}
			$queued = G2A_CRM_Messages_Repository::find( (int) $queued['id'] );
		}

		return $this->ok( $queued, 201 );
	}

	public function cancel( WP_REST_Request $request ) {
		$result = G2A_CRM_Messages_Repository::cancel( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function retry( WP_REST_Request $request ) {
		$result = G2A_CRM_Messages_Repository::retry( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function dispatch_now() {
		$count = G2A_CRM_Message_Dispatcher::run( 25 );
		return $this->ok( array( 'processed' => $count ) );
	}
}
