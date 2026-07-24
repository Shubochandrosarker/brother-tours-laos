<?php
/**
 * WooCommerce Subscriptions → CRM memberships sync.
 *
 * Only boots if the WooCommerce Subscriptions plugin is active (the paid
 * extension; class WC_Subscriptions or function wcs_get_subscription must
 * exist). When active, lifecycle transitions on a subscription mirror to
 * the CRM membership for the matched customer:
 *
 *   active / pending → create or resume CRM membership
 *   on-hold          → suspend
 *   cancelled        → cancel
 *   expired          → mark expired
 *   renewal_payment  → extend renewal_date by the billing interval
 *
 * Mapping is stored in wp_g2a_crm_integrations keyed by
 * (provider=woocommerce, external_type=subscription, external_id=<sub_id>)
 * with local_type=membership, local_id=<membership_id> so the link
 * survives across requests without a schema change.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_WooCommerce_Subscriptions_Sync {

	const PROVIDER      = 'woocommerce';
	const EXTERNAL_TYPE = 'subscription';

	public static function boot() {
		if ( ! self::is_active() ) {
			return;
		}
		// Status transitions: status_<new>
		add_action( 'woocommerce_subscription_status_active', array( __CLASS__, 'on_active' ), 20, 1 );
		add_action( 'woocommerce_subscription_status_on-hold', array( __CLASS__, 'on_hold' ), 20, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( __CLASS__, 'on_cancelled' ), 20, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( __CLASS__, 'on_expired' ), 20, 1 );

		// Successful renewal payment extends the membership renewal_date.
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( __CLASS__, 'on_renewal_paid' ), 20, 1 );
	}

	public static function is_active() {
		return function_exists( 'wcs_get_subscription' ) || class_exists( 'WC_Subscriptions' );
	}

	public static function on_active( $subscription ) {
		$customer = self::resolve_customer( $subscription );
		if ( ! $customer ) {
			return;
		}

		$membership_id = self::existing_membership_id( $subscription );
		if ( $membership_id ) {
			G2A_CRM_Memberships_Repository::update( $membership_id, array( 'status' => 'active' ) );
			return;
		}

		// Create a fresh membership. If the customer already has an active one,
		// the repository will 409 — log and skip rather than fight it.
		$result = G2A_CRM_Memberships_Repository::create(
			array(
				'customer_id'    => (int) $customer['id'],
				'plan_name'      => self::plan_name( $subscription ),
				'status'         => 'active',
				'start_date'     => self::date_string( method_exists( $subscription, 'get_date' ) ? $subscription->get_date( 'start' ) : null ),
				'renewal_date'   => self::date_string( method_exists( $subscription, 'get_date' ) ? $subscription->get_date( 'next_payment' ) : null ),
				'monthly_amount' => self::monthly_amount( $subscription ),
			)
		);
		if ( is_wp_error( $result ) ) {
			error_log( 'G2A CRM: WCS active sync skipped — ' . $result->get_error_message() ); // phpcs:ignore
			return;
		}
		self::link( (int) $subscription->get_id(), (int) $result['id'] );
	}

	public static function on_hold( $subscription ) {
		$membership_id = self::existing_membership_id( $subscription );
		if ( $membership_id ) {
			G2A_CRM_Memberships_Repository::suspend( $membership_id, 'WooCommerce subscription on-hold' );
		}
	}

	public static function on_cancelled( $subscription ) {
		$membership_id = self::existing_membership_id( $subscription );
		if ( $membership_id ) {
			G2A_CRM_Memberships_Repository::cancel( $membership_id, 'WooCommerce subscription cancelled' );
		}
	}

	public static function on_expired( $subscription ) {
		$membership_id = self::existing_membership_id( $subscription );
		if ( $membership_id ) {
			G2A_CRM_Memberships_Repository::update( $membership_id, array( 'status' => 'expired' ) );
		}
	}

	/**
	 * Renewal payment cleared. Extend the membership's renewal_date to match
	 * the subscription's next_payment date. Falls back to +1 month.
	 */
	public static function on_renewal_paid( $subscription ) {
		$membership_id = self::existing_membership_id( $subscription );
		if ( ! $membership_id ) {
			// Out-of-band renewal payment without an active link — try to create one.
			self::on_active( $subscription );
			return;
		}
		$next = method_exists( $subscription, 'get_date' ) ? self::date_string( $subscription->get_date( 'next_payment' ) ) : null;
		if ( $next ) {
			G2A_CRM_Memberships_Repository::update(
				$membership_id,
				array(
					'status'         => 'active',
					'renewal_date'   => $next,
					'billing_status' => 'current',
				)
			);
		} else {
			G2A_CRM_Memberships_Repository::renew( $membership_id, 1 );
		}
	}

	/**
	 * Look up the CRM membership id linked to this subscription, if any.
	 *
	 * @return int 0 when unlinked.
	 */
	private static function existing_membership_id( $subscription ) {
		$row = G2A_CRM_Integrations_Repository::find_external(
			self::PROVIDER,
			self::EXTERNAL_TYPE,
			(string) $subscription->get_id()
		);
		if ( $row && 'membership' === $row['local_type'] ) {
			return (int) $row['local_id'];
		}
		return 0;
	}

	private static function link( $subscription_id, $membership_id ) {
		G2A_CRM_Integrations_Repository::upsert(
			array(
				'provider'      => self::PROVIDER,
				'external_type' => self::EXTERNAL_TYPE,
				'external_id'   => (string) $subscription_id,
				'local_type'    => 'membership',
				'local_id'      => (int) $membership_id,
				'payload'       => array( 'linked_at' => g2a_crm_now() ),
			)
		);
	}

	private static function resolve_customer( $subscription ) {
		$user_id = method_exists( $subscription, 'get_user_id' ) ? (int) $subscription->get_user_id() : 0;
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user && $user->user_email ) {
				$match = G2A_CRM_Customers_Repository::find_by_email( $user->user_email );
				if ( $match ) {
					return $match;
				}
			}
		}
		$email = method_exists( $subscription, 'get_billing_email' ) ? sanitize_email( (string) $subscription->get_billing_email() ) : '';
		if ( $email ) {
			return G2A_CRM_Customers_Repository::find_by_email( $email );
		}
		return null;
	}

	private static function plan_name( $subscription ) {
		if ( method_exists( $subscription, 'get_items' ) ) {
			foreach ( $subscription->get_items() as $item ) {
				if ( method_exists( $item, 'get_name' ) ) {
					return (string) $item->get_name();
				}
			}
		}
		return 'WooCommerce Subscription #' . (int) $subscription->get_id();
	}

	/**
	 * Best-effort monthly equivalent of the subscription's recurring total.
	 * Yearly billing → /12; weekly → *4.33; daily → *30; everything else → as-is.
	 */
	private static function monthly_amount( $subscription ) {
		if ( ! method_exists( $subscription, 'get_total' ) || ! method_exists( $subscription, 'get_billing_period' ) || ! method_exists( $subscription, 'get_billing_interval' ) ) {
			return 0;
		}
		$total    = (float) $subscription->get_total();
		$period   = (string) $subscription->get_billing_period();
		$interval = max( 1, (int) $subscription->get_billing_interval() );
		$per      = $total / $interval;
		switch ( $period ) {
			case 'year':
				return $per / 12;
			case 'month':
				return $per;
			case 'week':
				return $per * 4.345;
			case 'day':
				return $per * 30;
		}
		return $per;
	}

	private static function date_string( $value ) {
		if ( ! $value ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d', (int) $value );
		}
		$ts = strtotime( (string) $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}
}
