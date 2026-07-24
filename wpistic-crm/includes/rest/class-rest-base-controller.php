<?php
/**
 * Base REST controller. Subclasses register their own routes.
 *
 * Permission callbacks here standardize the auth check used across endpoints.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class G2A_CRM_REST_Base_Controller {

	protected $namespace = G2A_CRM_REST_NAMESPACE;

	abstract public function register_routes();

	/**
	 * Permission callback factory — returns a closure for register_rest_route().
	 *
	 * @param string $cap Capability required for the route.
	 * @return callable
	 */
	protected function require_cap( $cap ) {
		return static function () use ( $cap ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'g2a_crm_unauthenticated', __( 'You must be logged in.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $cap ) ) {
				return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to perform this action.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
			}
			return true;
		};
	}

	protected function ok( $data, $status = 200 ) {
		return new WP_REST_Response( $data, $status );
	}

	protected function paginated( array $result, $page, $per_page ) {
		$total = (int) ( $result['total'] ?? 0 );
		$response = new WP_REST_Response(
			array(
				'data'  => $result['rows'] ?? array(),
				'meta'  => array(
					'page'        => (int) $page,
					'per_page'    => (int) $per_page,
					'total'       => $total,
					'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
				),
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ( $per_page > 0 ? (int) ceil( $total / $per_page ) : 0 ) );
		return $response;
	}
}
