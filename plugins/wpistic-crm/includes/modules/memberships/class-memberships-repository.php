<?php
/**
 * Memberships data access layer.
 *
 * Membership status changes mirror onto the customer's `membership_status` so
 * range staff and the dashboard can see member state without joining.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Memberships_Repository {

	const STATUSES        = array( 'active', 'trial', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended', 'vip' );
	const BILLING_STATUSES = array( 'current', 'past_due', 'failed', 'paused', 'comp' );
	const ACCESS_LEVELS    = array( 'standard', 'premium', 'family', 'corporate', 'lifetime' );

	public static function table() {
		return g2a_crm_table( 'memberships' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_active_for_customer( $customer_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE customer_id = %d AND status IN ("active","trial","vip") ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $customer_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]    = 'customer_id = %d';
			$prepared[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['billing_status'] ) ) {
			$where[]    = 'billing_status = %s';
			$prepared[] = sanitize_key( $args['billing_status'] );
		}
		if ( ! empty( $args['renews_within_days'] ) ) {
			$horizon    = gmdate( 'Y-m-d', strtotime( '+' . (int) $args['renews_within_days'] . ' days' ) );
			$where[]    = 'renewal_date IS NOT NULL AND renewal_date <= %s AND status IN ("active","trial","past_due")';
			$prepared[] = $horizon;
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				array_merge( $prepared, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	public static function create( array $data ) {
		global $wpdb;
		$row = self::sanitize( $data );
		if ( empty( $row['customer_id'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Customer is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['plan_name'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Plan name is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['start_date'] ) ) {
			$row['start_date'] = gmdate( 'Y-m-d' );
		}

		// One active/trial membership per customer at a time.
		$existing = self::find_active_for_customer( (int) $row['customer_id'] );
		if ( $existing ) {
			return new WP_Error( 'g2a_crm_conflict', __( 'Customer already has an active membership. Cancel or suspend it first.', 'guns2ammo-crm' ), array( 'status' => 409, 'existing_id' => (int) $existing['id'] ) );
		}

		$now               = g2a_crm_now();
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );

		self::sync_customer_status( (int) $new['customer_id'], $new['status'] );
		G2A_CRM_Audit_Log::record( 'membership.create', 'membership', $id, null, $new );

		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'membership.update', 'membership', (int) $id, $diff['old'], $diff['new'] );
		}
		if ( isset( $diff['new']['status'] ) ) {
			self::sync_customer_status( (int) $new['customer_id'], $new['status'] );
		}
		return $new;
	}

	public static function cancel( $id, $reason = '' ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$now = g2a_crm_now();
		$wpdb->update(
			self::table(),
			array(
				'status'              => 'cancelled',
				'cancelled_at'        => $now,
				'cancellation_reason' => sanitize_textarea_field( $reason ),
				'updated_at'          => $now,
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		self::sync_customer_status( (int) $new['customer_id'], 'cancelled' );
		G2A_CRM_Audit_Log::record( 'membership.cancel', 'membership', (int) $id, $existing, $new, $reason );
		return $new;
	}

	public static function renew( $id, $months = 1 ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$months = max( 1, min( 60, (int) $months ) );
		$base   = $existing['renewal_date'] && strtotime( $existing['renewal_date'] ) > time()
			? $existing['renewal_date']
			: gmdate( 'Y-m-d' );
		$next   = gmdate( 'Y-m-d', strtotime( "+{$months} months", strtotime( $base ) ) );

		global $wpdb;
		$now = g2a_crm_now();
		$wpdb->update(
			self::table(),
			array(
				'renewal_date'   => $next,
				'status'         => 'active',
				'billing_status' => 'current',
				'updated_at'     => $now,
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		self::sync_customer_status( (int) $new['customer_id'], 'active' );
		G2A_CRM_Audit_Log::record( 'membership.renew', 'membership', (int) $id, $existing, $new, sprintf( '+%d months', $months ) );
		return $new;
	}

	public static function suspend( $id, $reason = '' ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'suspended',
				'updated_at' => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		self::sync_customer_status( (int) $new['customer_id'], 'suspended' );
		G2A_CRM_Audit_Log::record( 'membership.suspend', 'membership', (int) $id, $existing, $new, $reason );
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Membership not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		// Re-derive customer status from remaining memberships, if any.
		$remaining = self::find_active_for_customer( (int) $existing['customer_id'] );
		self::sync_customer_status( (int) $existing['customer_id'], $remaining ? $remaining['status'] : 'none' );
		G2A_CRM_Audit_Log::record( 'membership.delete', 'membership', (int) $id, $existing, null );
		return true;
	}

	/**
	 * Daily cron task: flip past-due renewals.
	 */
	public static function expire_due() {
		global $wpdb;
		$today    = gmdate( 'Y-m-d' );
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET status = "past_due", updated_at = %s WHERE status = "active" AND renewal_date IS NOT NULL AND renewal_date < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				g2a_crm_now(),
				$today
			)
		);
		if ( $affected > 0 ) {
			G2A_CRM_Audit_Log::record( 'membership.expire_batch', 'membership', 0, null, array( 'affected' => (int) $affected ) );
		}
		return (int) $affected;
	}

	private static function sync_customer_status( $customer_id, $status ) {
		global $wpdb;
		$wpdb->update(
			g2a_crm_table( 'customers' ),
			array(
				'membership_status' => sanitize_key( $status ),
				'last_activity_at'  => g2a_crm_now(),
			),
			array( 'id' => (int) $customer_id )
		);
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();
		if ( array_key_exists( 'customer_id', $data ) ) {
			$row['customer_id'] = (int) $data['customer_id'];
		}
		if ( array_key_exists( 'plan_name', $data ) ) {
			$row['plan_name'] = sanitize_text_field( (string) $data['plan_name'] );
		}
		if ( array_key_exists( 'access_level', $data ) ) {
			$row['access_level'] = g2a_crm_allowed_status( $data['access_level'], self::ACCESS_LEVELS, $existing['access_level'] ?? 'standard' );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'active' );
		}
		if ( array_key_exists( 'billing_status', $data ) ) {
			$row['billing_status'] = g2a_crm_allowed_status( $data['billing_status'], self::BILLING_STATUSES, $existing['billing_status'] ?? 'current' );
		}
		if ( array_key_exists( 'start_date', $data ) ) {
			$row['start_date'] = self::sanitize_date( $data['start_date'] );
		}
		if ( array_key_exists( 'renewal_date', $data ) ) {
			$row['renewal_date'] = self::sanitize_date( $data['renewal_date'] );
		}
		if ( array_key_exists( 'monthly_amount', $data ) ) {
			$row['monthly_amount'] = max( 0, (float) $data['monthly_amount'] );
		}
		if ( array_key_exists( 'payment_method_ref', $data ) ) {
			$row['payment_method_ref'] = sanitize_text_field( (string) $data['payment_method_ref'] );
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$row['notes'] = $data['notes'] ? wp_kses_post( (string) $data['notes'] ) : null;
		}
		return $row;
	}

	private static function sanitize_date( $value ) {
		if ( ! $value ) {
			return null;
		}
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) ? substr( $value, 0, 10 ) : null;
	}
}
