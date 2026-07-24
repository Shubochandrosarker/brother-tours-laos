<?php
/**
 * Classes REST controller — /g2a-crm/v1/classes
 *
 * Roster (enrollments) lives under /classes/{id}/students.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Classes_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/classes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_classes' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_classes' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/classes/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_view_classes' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_classes' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_edit_classes' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/classes/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_edit_classes' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/classes/(?P<id>\d+)/students',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'roster' ),
					'permission_callback' => $this->roster_permission(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_student' ),
					'permission_callback' => $this->require_cap( 'g2a_crm_manage_class_roster' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/classes/(?P<id>\d+)/students/(?P<student_id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_student' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_class_roster' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Classes_Repository::list(
			array(
				'page'               => $pg['page'],
				'per_page'           => $pg['per_page'],
				'status'             => (string) $request->get_param( 'status' ),
				'class_type'         => (string) $request->get_param( 'class_type' ),
				'instructor_id'      => (int) $request->get_param( 'instructor_id' ),
				'mine_as_instructor' => (bool) $request->get_param( 'mine_as_instructor' ),
				'from'               => (string) $request->get_param( 'from' ),
				'to'                 => (string) $request->get_param( 'to' ),
				'upcoming'           => (bool) $request->get_param( 'upcoming' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Classes_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$row['roster'] = G2A_CRM_Class_Students_Repository::roster( (int) $row['id'] );
		$row['enrolled'] = count( array_filter( $row['roster'], static fn( $r ) => 'cancelled' !== $r['status'] ) );
		$row['seats_left'] = (int) $row['capacity'] - $row['enrolled'];
		if ( $row['instructor_id'] ) {
			$user = get_userdata( (int) $row['instructor_id'] );
			$row['instructor_name'] = $user ? $user->display_name : '';
		}
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Classes_Repository::create( $payload );
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
		$result = G2A_CRM_Classes_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Classes_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}

	public function cancel( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$reason  = is_array( $payload ) ? (string) ( $payload['reason'] ?? '' ) : '';
		$result  = G2A_CRM_Classes_Repository::cancel( (int) $request['id'], $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function roster( WP_REST_Request $request ) {
		$class = G2A_CRM_Classes_Repository::find( (int) $request['id'] );
		if ( ! $class ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok(
			array(
				'class'   => $class,
				'roster' => G2A_CRM_Class_Students_Repository::roster( (int) $class['id'] ),
			)
		);
	}

	public function register_student( WP_REST_Request $request ) {
		$payload     = $request->get_json_params() ?: array();
		$customer_id = (int) ( $payload['customer_id'] ?? 0 );
		if ( ! $customer_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'customer_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$result = G2A_CRM_Class_Students_Repository::register( (int) $request['id'], $customer_id, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result, 201 );
	}

	public function update_student( WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: array();
		$result  = G2A_CRM_Class_Students_Repository::update( (int) $request['student_id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	/**
	 * Roster view is allowed to instructors regardless of generic edit-class cap.
	 */
	private function roster_permission() {
		return static function ( WP_REST_Request $request ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
			}
			if ( current_user_can( 'g2a_crm_view_classes' ) || current_user_can( 'g2a_crm_manage_class_roster' ) ) {
				return true;
			}
			// Instructor-of-record fallback.
			$class = G2A_CRM_Classes_Repository::find( (int) $request['id'] );
			if ( $class && (int) $class['instructor_id'] === get_current_user_id() ) {
				return true;
			}
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to view this roster.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		};
	}
}
