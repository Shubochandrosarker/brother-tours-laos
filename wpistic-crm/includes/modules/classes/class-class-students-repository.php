<?php
/**
 * Class roster (enrollments) data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Class_Students_Repository {

	const STATUSES        = array( 'registered', 'confirmed', 'checked_in', 'attended', 'completed', 'no_show', 'cancelled', 'needs_follow_up' );
	const PAYMENT_STATUSES = array( 'unpaid', 'paid', 'partial', 'refunded', 'comp' );

	public static function table() {
		return g2a_crm_table( 'class_students' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Roster for one class, joined with customer info.
	 */
	public static function roster( $class_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cs.*, c.first_name, c.last_name, c.email, c.phone, c.waiver_status FROM ' . self::table() . ' cs LEFT JOIN ' . G2A_CRM_Customers_Repository::table() . ' c ON c.id = cs.customer_id WHERE cs.class_id = %d ORDER BY cs.id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $class_id
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Class history for one customer, joined with class info.
	 */
	public static function for_customer( $customer_id, $limit = 25 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cs.*, cl.name AS class_name, cl.class_type, cl.start_time AS class_start_time FROM ' . self::table() . ' cs LEFT JOIN ' . G2A_CRM_Classes_Repository::table() . ' cl ON cl.id = cs.class_id WHERE cs.customer_id = %d ORDER BY cl.start_time DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $customer_id,
				max( 1, min( 100, (int) $limit ) )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	public static function register( $class_id, $customer_id, array $opts = array() ) {
		$class = G2A_CRM_Classes_Repository::find( $class_id );
		if ( ! $class ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( 'cancelled' === $class['status'] ) {
			return new WP_Error( 'g2a_crm_invalid_state', __( 'Class is cancelled.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		$customer = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		// Idempotency: if already enrolled, return existing record.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE class_id = %d AND customer_id = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $class_id,
				(int) $customer_id
			),
			ARRAY_A
		);
		if ( $existing ) {
			if ( 'cancelled' === $existing['status'] ) {
				// Re-activate a cancelled enrollment.
				$wpdb->update(
					self::table(),
					array( 'status' => 'registered', 'updated_at' => g2a_crm_now() ),
					array( 'id' => (int) $existing['id'] )
				);
				G2A_CRM_Audit_Log::record( 'class.student.reregister', 'class_student', (int) $existing['id'], $existing, self::find( $existing['id'] ) );
				return self::find( $existing['id'] );
			}
			return $existing;
		}

		// Capacity check.
		$enrolled = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE class_id = %d AND status NOT IN ("cancelled")', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $class_id
			)
		);
		if ( (int) $class['capacity'] > 0 && $enrolled >= (int) $class['capacity'] ) {
			return new WP_Error( 'g2a_crm_full', __( 'Class is at capacity.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		$now = g2a_crm_now();
		$row = array(
			'class_id'       => (int) $class_id,
			'customer_id'    => (int) $customer_id,
			'status'         => 'registered',
			'payment_status' => g2a_crm_allowed_status( $opts['payment_status'] ?? 'unpaid', self::PAYMENT_STATUSES, 'unpaid' ),
			'created_at'     => $now,
			'updated_at'     => $now,
		);
		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'class.student.register', 'class_student', $id, null, $new );
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Enrollment not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$patch = array( 'updated_at' => g2a_crm_now() );

		if ( array_key_exists( 'status', $data ) ) {
			$patch['status']   = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] );
			$patch['attended'] = in_array( $patch['status'], array( 'attended', 'completed', 'checked_in' ), true ) ? 1 : (int) $existing['attended'];
		}
		if ( array_key_exists( 'payment_status', $data ) ) {
			$patch['payment_status'] = g2a_crm_allowed_status( $data['payment_status'], self::PAYMENT_STATUSES, $existing['payment_status'] );
		}

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'class.student.update', 'class_student', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function cancel( $id ) {
		return self::update( $id, array( 'status' => 'cancelled' ) );
	}

	public static function mark_attended( $id ) {
		return self::update( $id, array( 'status' => 'attended' ) );
	}

	public static function mark_no_show( $id ) {
		return self::update( $id, array( 'status' => 'no_show' ) );
	}

	public static function mark_completed( $id ) {
		return self::update( $id, array( 'status' => 'completed' ) );
	}
}
