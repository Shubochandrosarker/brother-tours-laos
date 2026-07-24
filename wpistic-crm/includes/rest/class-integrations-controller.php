<?php
/**
 * Integrations REST controller — /g2a-crm/v1/integrations
 *
 * - GET  /integrations              List all sync records (filterable by provider).
 * - GET  /integrations/stats        Per-provider counts + last-sync timestamps.
 * - GET  /integrations/unlinked     Rows with no matching customer (manual reconcile).
 * - POST /integrations/woocommerce/sync          Manual single-order sync.
 * - POST /integrations/woocommerce/backfill      Bulk re-sync for a customer email.
 * - GET  /customers/{id}/purchases               Customer-scoped purchase history.
 *
 * The customer-scoped purchase route is also registered here (rather than the
 * customers controller) so the integration owns its surface area end-to-end.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Integrations_Controller extends G2A_CRM_REST_Base_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/integrations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_integrations' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/integrations/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'stats' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_integrations' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/integrations/unlinked',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_unlinked' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_integrations' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/integrations/woocommerce/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_order' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_integrations' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/integrations/woocommerce/backfill',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'backfill' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_manage_integrations' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/customers/(?P<id>\d+)/purchases',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer_purchases' ),
				'permission_callback' => $this->require_cap( 'g2a_crm_view_customers' ),
			)
		);
	}

	public function list_items( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Integrations_Repository::list(
			array(
				'page'          => $pg['page'],
				'per_page'      => $pg['per_page'],
				'provider'      => (string) $request->get_param( 'provider' ),
				'external_type' => (string) $request->get_param( 'external_type' ),
				'local_type'    => (string) $request->get_param( 'local_type' ),
				'local_id'      => (int) $request->get_param( 'local_id' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function stats() {
		return $this->ok(
			array(
				'providers'           => G2A_CRM_Integrations_Repository::stats(),
				'woocommerce_active'  => G2A_CRM_WooCommerce_Sync::is_woocommerce_active(),
			)
		);
	}

	public function list_unlinked( WP_REST_Request $request ) {
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Integrations_Repository::list(
			array(
				'page'       => $pg['page'],
				'per_page'   => $pg['per_page'],
				'provider'   => (string) $request->get_param( 'provider' ),
				'local_type' => 'unlinked',
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}

	public function sync_order( WP_REST_Request $request ) {
		if ( ! G2A_CRM_WooCommerce_Sync::is_woocommerce_active() ) {
			return new WP_Error( 'g2a_crm_woocommerce_inactive', __( 'WooCommerce is not active on this site.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}
		$order_id = (int) $request->get_param( 'order_id' );
		if ( ! $order_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'order_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$row = G2A_CRM_WooCommerce_Sync::sync_order( $order_id );
		if ( ! $row ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Order not found in WooCommerce.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		return $this->ok( $row );
	}

	public function backfill( WP_REST_Request $request ) {
		if ( ! G2A_CRM_WooCommerce_Sync::is_woocommerce_active() ) {
			return new WP_Error( 'g2a_crm_woocommerce_inactive', __( 'WooCommerce is not active on this site.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}
		$customer_id = (int) $request->get_param( 'customer_id' );
		if ( ! $customer_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'customer_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$customer = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$email = isset( $customer['email'] ) ? $customer['email'] : '';
		if ( ! $email ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Customer has no email on file to match against WooCommerce orders.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$synced = G2A_CRM_WooCommerce_Sync::backfill_for_email( $email, $customer_id );
		G2A_CRM_Audit_Log::record( 'integration.backfill', 'customer', $customer_id, null, array( 'provider' => 'woocommerce', 'orders' => $synced ) );
		return $this->ok(
			array(
				'customer_id' => $customer_id,
				'email'       => $email,
				'orders'      => $synced,
			)
		);
	}

	public function customer_purchases( WP_REST_Request $request ) {
		$customer_id = (int) $request['id'];
		$customer    = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$pg     = g2a_crm_parse_pagination( $request );
		$result = G2A_CRM_Integrations_Repository::list(
			array(
				'page'       => $pg['page'],
				'per_page'   => $pg['per_page'],
				'local_type' => 'customer',
				'local_id'   => $customer_id,
				'provider'   => (string) $request->get_param( 'provider' ),
			)
		);
		return $this->paginated( $result, $pg['page'], $pg['per_page'] );
	}
}
