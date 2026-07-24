<?php
/**
 * Sensitive Records REST controller — /g2a-crm/v1/sensitive-records
 *
 * Access is gated by TWO layers:
 *   1. WP capability (view_sensitive_records / edit_sensitive_records)
 *   2. Optional role allowlist in settings.restrict_sensitive_view_to_roles
 *
 * Every READ logs an audit event. The plugin already audit-logs writes; for
 * regulated records we also want a "who looked at this" trail.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Sensitive_Records_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/sensitive-records',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'check_view_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sensitive-records/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_view_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sensitive-records/meta',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_meta' ),
				'permission_callback' => array( $this, 'check_view_permission' ),
			)
		);
	}

	public function check_view_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'g2a_crm_view_sensitive_records' ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to view sensitive records.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		if ( ! G2A_CRM_Sensitive_Records_Repository::role_allowed_for_current_user() ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'Your role is not on the sensitive-records allowlist.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_edit_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'g2a_crm_edit_sensitive_records' ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to edit sensitive records.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		if ( ! G2A_CRM_Sensitive_Records_Repository::role_allowed_for_current_user() ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'Your role is not on the sensitive-records allowlist.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$args   = array(
			'page'        => $pg['page'],
			'per_page'    => $pg['per_page'],
			'customer_id' => (int) $request->get_param( 'customer_id' ),
			'record_type' => (string) $request->get_param( 'record_type' ),
			'status'      => (string) $request->get_param( 'status' ),
			'open_only'   => (bool) $request->get_param( 'open_only' ),
			'handled_by'  => (int) $request->get_param( 'handled_by' ),
			'search'      => (string) $request->get_param( 'search' ),
		);
		$result = G2A_CRM_Sensitive_Records_Repository::list( $args );

		G2A_CRM_Sensitive_Records_Repository::log_read(
			'list',
			0,
			array(
				'filters' => array_filter(
					$args,
					static function ( $v, $k ) {
						return 'per_page' !== $k && 'page' !== $k && ! empty( $v );
					},
					ARRAY_FILTER_USE_BOTH
				),
				'result_count' => count( $result['rows'] ),
			)
		);

		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Sensitive_Records_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Sensitive record not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		G2A_CRM_Sensitive_Records_Repository::log_read(
			'view',
			(int) $row['id'],
			array(
				'customer_id' => (int) $row['customer_id'],
				'record_type' => $row['record_type'],
			)
		);
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		// Default handled_by to the creating user if not set.
		if ( empty( $payload['handled_by'] ) ) {
			$payload['handled_by'] = get_current_user_id();
		}
		$result = G2A_CRM_Sensitive_Records_Repository::create( $payload );
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
		$result = G2A_CRM_Sensitive_Records_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Sensitive_Records_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function get_meta() {
		return $this->ok(
			array(
				'record_types'  => G2A_CRM_Sensitive_Records_Repository::RECORD_TYPES,
				'statuses'      => G2A_CRM_Sensitive_Records_Repository::STATUSES,
				'open_statuses' => G2A_CRM_Sensitive_Records_Repository::OPEN_STATUSES,
			)
		);
	}
}
