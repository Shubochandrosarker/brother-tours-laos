<?php
/**
 * Leads REST controller — /g2a-crm/v1/leads
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Leads_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/leads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_leads' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_leads' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_leads' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_leads' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_delete_leads' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/convert',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'convert' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_leads' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/activity',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_activity' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_leads' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_activity' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_leads' ),
				),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Leads_Repository::list(
			array(
				'page'          => $pg['page'],
				'per_page'      => $pg['per_page'],
				'search'        => (string) $request->get_param( 'search' ),
				'status'        => (string) $request->get_param( 'status' ),
				'interest_type' => (string) $request->get_param( 'interest_type' ),
				'assigned_to'   => (int) $request->get_param( 'assigned_to' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Leads_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Leads_Repository::create( $payload );
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
		$result = G2A_CRM_Leads_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Leads_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function convert( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$result = G2A_CRM_Leads_Repository::convert( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function list_activity( WP_REST_Request $request ) {
		$lead = G2A_CRM_Leads_Repository::find( (int) $request['id'] );
		if ( ! $lead ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok(
			array(
				'lead'       => $lead,
				'activities' => G2A_CRM_Leads_Repository::activities( (int) $request['id'], (int) $request->get_param( 'limit' ) ?: 50 ),
			)
		);
	}

	public function add_activity( WP_REST_Request $request ) {
		$lead = G2A_CRM_Leads_Repository::find( (int) $request['id'] );
		if ( ! $lead ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$summary = (string) ( $payload['summary'] ?? '' );
		$type    = (string) ( $payload['activity_type'] ?? 'note' );
		$body    = $payload['body'] ?? null;
		if ( '' === trim( $summary ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Summary is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		G2A_CRM_Leads_Repository::activity( (int) $request['id'], $type, $summary, $body );
		return $this->ok(
			array(
				'activities' => G2A_CRM_Leads_Repository::activities( (int) $request['id'] ),
			),
			201
		);
	}
}
