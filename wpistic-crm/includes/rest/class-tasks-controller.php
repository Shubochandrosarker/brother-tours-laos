<?php
/**
 * Tasks REST controller — /g2a-crm/v1/tasks
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Tasks_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_tasks' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_tasks' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_tasks' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_tasks' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_tasks' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_tasks' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Tasks_Repository::list(
			array(
				'page'         => $pg['page'],
				'per_page'     => $pg['per_page'],
				'status'       => (string) $request->get_param( 'status' ),
				'assigned_to'  => (int) $request->get_param( 'assigned_to' ),
				'related_type' => (string) $request->get_param( 'related_type' ),
				'related_id'   => (int) $request->get_param( 'related_id' ),
				'mine'         => (bool) $request->get_param( 'mine' ),
				'overdue'      => (bool) $request->get_param( 'overdue' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Tasks_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Task not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Tasks_Repository::create( $payload );
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
		$result = G2A_CRM_Tasks_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Tasks_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function complete( WP_REST_Request $request ) {
		$result = G2A_CRM_Tasks_Repository::complete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}
}
