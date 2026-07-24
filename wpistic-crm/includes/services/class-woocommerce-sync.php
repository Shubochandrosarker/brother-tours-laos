<?php
/**
 * WooCommerce → CRM purchase sync.
 *
 * Listens for Woo order events and mirrors each order into wp_g2a_crm_integrations
 * keyed by (woocommerce, order, <order_id>). The local_id points at the matched
 * customer row (by billing email, falling back to linked WP user email). If no
 * match is found the row is parked with local_type='unlinked' so the dashboard
 * can surface it for manual resolution.
 *
 * SAFETY:
 * - Order line item details are NOT mirrored — only aggregate totals, status,
 *   item count, currency. Anything sensitive (FFL items, serials) stays in Woo;
 *   the CRM only gets a reference + amount.
 * - The sync is one-way (Woo → CRM). Nothing in this service writes to Woo.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_WooCommerce_Sync {

	const PROVIDER      = 'woocommerce';
	const EXTERNAL_TYPE = 'order';

	public static function boot() {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_order_changed' ), 20, 1 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'on_order_changed' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_order_refunded' ), 20, 2 );

		G2A_CRM_WooCommerce_Subscriptions_Sync::boot();
	}

	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Triggered on order create/update. Idempotent — relies on upsert.
	 *
	 * @param int $order_id
	 */
	public static function on_order_changed( $order_id ) {
		self::sync_order( (int) $order_id );
	}

	/**
	 * Triggered when a refund is created against an order. The refund itself is
	 * also a Woo order (post type shop_order_refund) — we mirror the parent
	 * order again so payload->refunded_total reflects the new state, and audit
	 * the refund event distinctly on the customer timeline.
	 *
	 * @param int $order_id  Parent order.
	 * @param int $refund_id Refund order id (unused but provided by hook).
	 */
	public static function on_order_refunded( $order_id, $refund_id ) {
		$record = self::sync_order( (int) $order_id );
		if ( $record && isset( $record['local_type'] ) && 'customer' === $record['local_type'] ) {
			G2A_CRM_Audit_Log::record(
				'woocommerce_order.refunded',
				'customer',
				(int) $record['local_id'],
				null,
				array(
					'order_id'  => (int) $order_id,
					'refund_id' => (int) $refund_id,
					'refunded'  => isset( $record['payload']['refunded_total'] ) ? $record['payload']['refunded_total'] : null,
				)
			);
		}
	}

	/**
	 * Triggered on status transition. Records the snapshot AND audits the change
	 * so it appears in the customer timeline distinctly from a generic update.
	 */
	public static function on_order_status_changed( $order_id, $from_status, $to_status, $order ) {
		$record = self::sync_order( (int) $order_id );
		if ( $record && isset( $record['local_type'] ) && 'customer' === $record['local_type'] ) {
			G2A_CRM_Audit_Log::record(
				'woocommerce_order.status_change',
				'customer',
				(int) $record['local_id'],
				array( 'status' => $from_status ),
				array(
					'status'   => $to_status,
					'order_id' => (int) $order_id,
				)
			);
		}
	}

	/**
	 * Fetch and upsert. Returns the resulting integrations row (or null on hard skip).
	 *
	 * @param int $order_id
	 * @return array|null
	 */
	public static function sync_order( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$payload    = self::extract_payload( $order );
		$resolution = self::resolve_customer( $order );

		$data = array(
			'provider'      => self::PROVIDER,
			'external_type' => self::EXTERNAL_TYPE,
			'external_id'   => (string) $order_id,
			'local_type'    => $resolution['local_type'],
			'local_id'      => $resolution['local_id'],
			'payload'       => $payload,
		);

		$row = G2A_CRM_Integrations_Repository::upsert( $data );
		if ( is_wp_error( $row ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Re-resolve and sync every order for a single customer email — used after a
	 * customer record is created or merged so retroactive history attaches.
	 *
	 * @param string $email
	 * @param int    $customer_id
	 * @return int rows updated
	 */
	public static function backfill_for_email( $email, $customer_id ) {
		if ( ! self::is_woocommerce_active() || ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$email = sanitize_email( $email );
		if ( ! $email ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => 200,
				'billing_email'  => $email,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
			)
		);
		if ( empty( $orders ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $orders as $oid ) {
			if ( self::sync_order( (int) $oid ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Build the snapshot payload for an order. Keep this small and stable.
	 *
	 * Line items intentionally OMIT SKU, serial, product_id, and any meta — for
	 * a compliance-sensitive store the SKU itself can leak regulated info into
	 * the CRM audit trail. Only the human-readable product name, quantity, and
	 * line total are mirrored so staff can recognize the order. Anything more
	 * detailed stays in WooCommerce.
	 */
	private static function extract_payload( $order ) {
		$items = method_exists( $order, 'get_item_count' ) ? (int) $order->get_item_count() : 0;
		return array(
			'order_number'   => method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $order->get_id(),
			'status'         => $order->get_status(),
			'total'          => (float) $order->get_total(),
			'subtotal'       => (float) $order->get_subtotal(),
			'currency'       => $order->get_currency(),
			'items_count'    => $items,
			'line_items'     => self::extract_line_items( $order ),
			'refunded_total' => method_exists( $order, 'get_total_refunded' ) ? (float) $order->get_total_refunded() : 0.0,
			'billing_email'  => $order->get_billing_email(),
			'billing_phone'  => $order->get_billing_phone(),
			'billing_first'  => $order->get_billing_first_name(),
			'billing_last'   => $order->get_billing_last_name(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'date_paid'      => $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d H:i:s' ) : null,
			'payment_method' => $order->get_payment_method_title(),
			'edit_url'       => function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' . (int) $order->get_id() . '&action=edit' ) : '',
		);
	}

	/**
	 * Mirror only name + qty + line total. Cap at 20 lines to keep the payload
	 * bounded for high-volume retail orders.
	 *
	 * @return array
	 */
	private static function extract_line_items( $order ) {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return array();
		}
		$out = array();
		$i   = 0;
		foreach ( $order->get_items() as $item ) {
			if ( ++$i > 20 ) {
				break;
			}
			$out[] = array(
				'name'  => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
				'qty'   => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 0,
				'total' => method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0,
			);
		}
		return $out;
	}

	/**
	 * Resolve a Woo order to a CRM customer row.
	 *
	 * Match order:
	 *   1. linked WP user → user's email → CRM customer (always trusted)
	 *   2. billing_email exact match (only when no verified-user requirement)
	 *
	 * The setting `woo_require_verified_user_link` prevents step 2 — orders from
	 * guest checkout (no user_id) get local_type='guest_link' so an attacker
	 * can't pollute a real customer's purchase history just by entering their
	 * email at checkout. Default off to preserve existing behavior; ops can
	 * flip it on for high-value installs.
	 *
	 * @return array{local_type:string,local_id:int}
	 */
	private static function resolve_customer( $order ) {
		$billing_email = sanitize_email( (string) $order->get_billing_email() );
		$user_id       = (int) $order->get_user_id();
		$verified_only = (bool) G2A_CRM_Settings::get( 'woo_require_verified_user_link', false );

		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user && $user->user_email ) {
				$match = G2A_CRM_Customers_Repository::find_by_email( $user->user_email );
				if ( $match ) {
					return array( 'local_type' => 'customer', 'local_id' => (int) $match['id'] );
				}
			}
		}

		if ( $billing_email ) {
			$match = G2A_CRM_Customers_Repository::find_by_email( $billing_email );
			if ( $match ) {
				if ( $verified_only && ! $user_id ) {
					// Guest checkout under an existing customer's email — don't trust the link.
					return array( 'local_type' => 'guest_link', 'local_id' => (int) $match['id'] );
				}
				return array( 'local_type' => 'customer', 'local_id' => (int) $match['id'] );
			}
		}

		return array( 'local_type' => 'unlinked', 'local_id' => 0 );
	}
}
