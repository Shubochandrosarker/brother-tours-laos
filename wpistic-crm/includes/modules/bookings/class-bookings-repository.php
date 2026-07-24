<?php
/**
 * Bookings data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Bookings_Repository {

	const TYPES = array( 'range_lane', 'class', 'private_training', 'consultation', 'event', 'member_only', 'offline' );
	const STATUSES = array( 'pending', 'confirmed', 'checked_in', 'completed', 'no_show', 'cancelled', 'rescheduled', 'refunded', 'needs_review' );
	const PAYMENT_STATUSES = array( 'unpaid', 'paid', 'partial', 'refunded', 'comp' );
	const RESOURCE_TYPES = array( 'lane', 'instructor', 'room', 'class', 'none' );

	public static function table() {
		return g2a_crm_table( 'bookings' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
		if ( ! empty( $args['booking_type'] ) ) {
			$where[]    = 'booking_type = %s';
			$prepared[] = sanitize_key( $args['booking_type'] );
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]    = 'start_time >= %s';
			$prepared[] = self::sanitize_dt( $args['from'] ) ?: g2a_crm_now();
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]    = 'start_time <= %s';
			$prepared[] = self::sanitize_dt( $args['to'] ) ?: g2a_crm_now();
		}
		if ( ! empty( $args['today'] ) ) {
			$start_of_day = gmdate( 'Y-m-d 00:00:00' );
			$end_of_day   = gmdate( 'Y-m-d 23:59:59' );
			$where[]      = 'start_time BETWEEN %s AND %s';
			$prepared[]   = $start_of_day;
			$prepared[]   = $end_of_day;
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY start_time ASC LIMIT %d OFFSET %d",
				array_merge( $prepared, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Range of bookings for calendar rendering. Capped at 500 to keep payloads sane.
	 */
	public static function in_range( $from, $to ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, customer_id, booking_type, resource_type, resource_id, start_time, end_time, status FROM ' . self::table() . ' WHERE start_time BETWEEN %s AND %s ORDER BY start_time ASC LIMIT 500', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::sanitize_dt( $from ),
				self::sanitize_dt( $to )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	public static function create( array $data ) {
		global $wpdb;

		$row = self::sanitize( $data );
		if ( empty( $row['customer_id'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Customer is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['start_time'] ) || empty( $row['end_time'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Start and end times are required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( strtotime( $row['end_time'] ) <= strtotime( $row['start_time'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'End time must be after start time.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		// Conflict check for lane-style resources.
		if ( ! empty( $row['resource_type'] ) && 'none' !== $row['resource_type'] && ! empty( $row['resource_id'] ) ) {
			$conflict = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::table() . ' WHERE resource_type = %s AND resource_id = %d AND status NOT IN ("cancelled","refunded","no_show") AND start_time < %s AND end_time > %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$row['resource_type'],
					$row['resource_id'],
					$row['end_time'],
					$row['start_time']
				)
			);
			if ( $conflict ) {
				return new WP_Error( 'g2a_crm_conflict', __( 'This resource is already booked in that window.', 'guns2ammo-crm' ), array( 'status' => 409, 'conflict_id' => (int) $conflict ) );
			}
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
		G2A_CRM_Audit_Log::record( 'booking.create', 'booking', $id, null, $new );

		// Mirror activity onto the customer record's last_activity_at.
		$wpdb->update( g2a_crm_table( 'customers' ), array( 'last_activity_at' => $now ), array( 'id' => (int) $new['customer_id'] ) );

		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'booking.update', 'booking', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	/**
	 * Check a customer in to their booking — verifies waiver if configured.
	 */
	public static function check_in( $id, $opts = array() ) {
		$booking = self::find( $id );
		if ( ! $booking ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( in_array( $booking['status'], array( 'cancelled', 'refunded', 'no_show' ), true ) ) {
			return new WP_Error( 'g2a_crm_invalid_state', __( 'Cannot check in a cancelled or no-show booking.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		// Waiver gate (configurable per blueprint §3 — "configurable compliance settings").
		if ( empty( $opts['skip_waiver_check'] ) ) {
			$waiver_required_types = (array) G2A_CRM_Settings::get( 'waiver_required_booking_types', array( 'range_lane', 'class', 'private_training' ) );
			if ( in_array( $booking['booking_type'], $waiver_required_types, true ) ) {
				$valid = G2A_CRM_Waivers_Repository::has_valid_waiver( (int) $booking['customer_id'] );
				if ( ! $valid ) {
					return new WP_Error( 'g2a_crm_waiver_required', __( 'Customer needs a valid signed waiver before check-in.', 'guns2ammo-crm' ), array( 'status' => 409, 'reason' => 'waiver_required' ) );
				}
			}
		}

		global $wpdb;
		$now = g2a_crm_now();
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'checked_in',
				'updated_at' => $now,
			),
			array( 'id' => (int) $id )
		);
		$wpdb->update( g2a_crm_table( 'customers' ), array( 'last_activity_at' => $now ), array( 'id' => (int) $booking['customer_id'] ) );

		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'booking.check_in', 'booking', (int) $id, $booking, $new );
		return $new;
	}

	public static function cancel( $id, $reason = '' ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'cancelled',
				'notes'      => trim( ( $existing['notes'] ?? '' ) . ( $reason ? "\n[cancelled] " . sanitize_textarea_field( $reason ) : '' ) ),
				'updated_at' => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'booking.cancel', 'booking', (int) $id, $existing, $new, $reason );
		return $new;
	}

	public static function complete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'completed',
				'updated_at' => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'booking.complete', 'booking', (int) $id, $existing, $new );
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Booking not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'booking.delete', 'booking', (int) $id, $existing, null );
		return true;
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();
		if ( array_key_exists( 'customer_id', $data ) ) {
			$row['customer_id'] = (int) $data['customer_id'];
		}
		if ( array_key_exists( 'booking_type', $data ) ) {
			$row['booking_type'] = g2a_crm_allowed_status( $data['booking_type'], self::TYPES, $existing['booking_type'] ?? 'range_lane' );
		}
		if ( array_key_exists( 'resource_type', $data ) ) {
			$row['resource_type'] = g2a_crm_allowed_status( $data['resource_type'], self::RESOURCE_TYPES, $existing['resource_type'] ?? 'lane' );
		}
		if ( array_key_exists( 'resource_id', $data ) ) {
			$row['resource_id'] = $data['resource_id'] ? (int) $data['resource_id'] : null;
		}
		if ( array_key_exists( 'start_time', $data ) ) {
			$row['start_time'] = self::sanitize_dt( $data['start_time'] );
		}
		if ( array_key_exists( 'end_time', $data ) ) {
			$row['end_time'] = self::sanitize_dt( $data['end_time'] );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'pending' );
		}
		if ( array_key_exists( 'payment_status', $data ) ) {
			$row['payment_status'] = g2a_crm_allowed_status( $data['payment_status'], self::PAYMENT_STATUSES, $existing['payment_status'] ?? 'unpaid' );
		}
		if ( array_key_exists( 'amount', $data ) ) {
			$row['amount'] = (float) $data['amount'];
		}
		if ( array_key_exists( 'staff_id', $data ) ) {
			$row['staff_id'] = $data['staff_id'] ? (int) $data['staff_id'] : null;
		}
		if ( array_key_exists( 'external_ref', $data ) ) {
			$row['external_ref'] = sanitize_text_field( (string) $data['external_ref'] );
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$row['notes'] = $data['notes'] ? wp_kses_post( (string) $data['notes'] ) : null;
		}
		return $row;
	}

	private static function sanitize_dt( $value ) {
		if ( ! $value ) {
			return null;
		}
		$value = sanitize_text_field( (string) $value );
		$ts    = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
