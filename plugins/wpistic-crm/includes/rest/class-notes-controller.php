<?php
/**
 * Notes REST controller — /g2a-crm/v1/notes
 *
 * Notes are polymorphic. Permission is derived from the underlying record's
 * related_type: a note on a customer requires view_customers / edit_customers,
 * a note on a booking requires view_bookings / edit_bookings, etc.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Notes_Controller extends G2A_CRM_REST_Base_Controller {

	const VIEW_MAP = array(
		'customer'   => 'g2a_crm_view_customers',
		'lead'       => 'g2a_crm_view_leads',
		'booking'    => 'g2a_crm_view_bookings',
		'class'      => 'g2a_crm_view_classes',
		'membership' => 'g2a_crm_view_memberships',
		'waiver'     => 'g2a_crm_view_waivers',
	);

	const EDIT_MAP = array(
		'customer'   => 'g2a_crm_edit_customers',
		'lead'       => 'g2a_crm_edit_leads',
		'booking'    => 'g2a_crm_edit_bookings',
		'class'      => 'g2a_crm_edit_classes',
		'membership' => 'g2a_crm_edit_memberships',
		'waiver'     => 'g2a_crm_verify_waivers',
	);

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'check_list_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_create_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notes/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_view_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_item_edit_permission' ),
				),
			)
		);
	}

	public function check_list_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		$type = sanitize_key( (string) $request->get_param( 'related_type' ) );
		if ( ! $type ) {
			return new WP_Error( 'g2a_crm_validation', __( 'related_type is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$cap = self::VIEW_MAP[ $type ] ?? '';
		if ( ! $cap || ! current_user_can( $cap ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to view these notes.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_create_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$type = sanitize_key( (string) ( $payload['related_type'] ?? '' ) );
		$cap  = self::EDIT_MAP[ $type ] ?? '';
		if ( ! $cap || ! current_user_can( $cap ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to add notes here.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_item_view_permission( WP_REST_Request $request ) {
		return $this->check_existing_note_cap( (int) $request['id'], self::VIEW_MAP );
	}

	public function check_item_edit_permission( WP_REST_Request $request ) {
		return $this->check_existing_note_cap( (int) $request['id'], self::EDIT_MAP );
	}

	private function check_existing_note_cap( $id, array $map ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		$row = G2A_CRM_Notes_Repository::find( $id );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Note not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$cap = $map[ $row['related_type'] ] ?? '';
		if ( ! $cap || ! current_user_can( $cap ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission for this note.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Notes_Repository::list(
			array(
				'page'         => $pg['page'],
				'per_page'     => $pg['per_page'],
				'related_type' => (string) $request->get_param( 'related_type' ),
				'related_id'   => (int) $request->get_param( 'related_id' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function get_item( WP_REST_Request $request ) {
		$row = G2A_CRM_Notes_Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Note not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $row );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}
		$result = G2A_CRM_Notes_Repository::create( $payload );
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
		$result = G2A_CRM_Notes_Repository::update( (int) $request['id'], $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( $result );
	}

	public function delete_item( WP_REST_Request $request ) {
		$result = G2A_CRM_Notes_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->ok( array( 'deleted' => true ) );
	}
}
