<?php
/**
 * Audit logs REST controller — /g2a-crm/v1/audit-logs
 *
 * Read-only by design. Audit entries are written by services, never via HTTP.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Audit_Logs_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/audit-logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_audit_logs' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Audit_Log::list(
			array(
				'page'        => $pg['page'],
				'per_page'    => $pg['per_page'],
				'object_type' => (string) $request->get_param( 'object_type' ),
				'object_id'   => (int) $request->get_param( 'object_id' ),
				'actor_id'    => (int) $request->get_param( 'actor_id' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}
}
