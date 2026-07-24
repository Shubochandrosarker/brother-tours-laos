<?php
/**
 * Customers data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Customers_Repository {

	const TYPES = array(
		'walk_in',
		'range_visitor',
		'member',
		'class_student',
		'retail',
		'ffl_transfer',
		'lead',
		'corporate',
		'vip',
		'inactive',
	);

	const STATUSES = array( 'active', 'archived', 'banned' );

	const WAIVER_STATUSES = array( 'not_submitted', 'submitted', 'verified', 'expired', 'needs_update' );

	const MEMBERSHIP_STATUSES = array( 'none', 'active', 'trial', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended', 'vip' );

	const CONTACT_METHODS = array( 'email', 'sms', 'phone' );

	public static function table() {
		return g2a_crm_table( 'customers' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_by_email( $email ) {
		if ( ! $email ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s LIMIT 1', sanitize_email( $email ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Paginated list with optional filters.
	 *
	 * @param array $args
	 * @return array{rows:array,total:int}
	 */
	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['search'] ) ) {
			$like       = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]    = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
		}
		if ( ! empty( $args['customer_type'] ) ) {
			$where[]    = 'customer_type = %s';
			$prepared[] = sanitize_key( $args['customer_type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( $count_sql, $prepared ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$order_by = self::safe_order_column( $args['orderby'] ?? 'updated_at' );
		$order    = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $prepared, array( $per_page, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Find likely duplicate customers for a given customer id. Matches by
	 * normalized email, normalized phone, and exact (first_name + last_name).
	 * Returns each candidate with a confidence score and the reasons matched.
	 *
	 * Scoring (max across reasons):
	 *   - email match → 100
	 *   - phone match → 90
	 *   - name match  → 50
	 *
	 * Archived/banned candidates are excluded unless include_inactive is set.
	 *
	 * @param int  $customer_id
	 * @param bool $include_inactive
	 * @return array
	 */
	public static function find_duplicates( $customer_id, $include_inactive = false ) {
		$customer = self::find( $customer_id );
		if ( ! $customer ) {
			return array();
		}

		global $wpdb;
		$table = self::table();

		$email = strtolower( trim( (string) $customer['email'] ) );
		$phone = g2a_crm_sanitize_phone( (string) $customer['phone'] );
		$first = strtolower( trim( (string) $customer['first_name'] ) );
		$last  = strtolower( trim( (string) $customer['last_name'] ) );

		$candidates = array();

		if ( $email ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id <> %d AND LOWER(email) = %s LIMIT 25", (int) $customer_id, $email ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				self::add_candidate( $candidates, $r, 100, 'email' );
			}
		}

		if ( $phone ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id <> %d AND phone <> '' AND phone = %s LIMIT 25", (int) $customer_id, $phone ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				self::add_candidate( $candidates, $r, 90, 'phone' );
			}
		}

		if ( $first && $last ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id <> %d AND LOWER(first_name) = %s AND LOWER(last_name) = %s LIMIT 25", (int) $customer_id, $first, $last ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			foreach ( $rows as $r ) {
				self::add_candidate( $candidates, $r, 50, 'name' );
			}
		}

		if ( ! $include_inactive ) {
			$candidates = array_filter(
				$candidates,
				static function ( $c ) {
					return 'active' === ( $c['customer']['status'] ?? 'active' );
				}
			);
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		return array_values( $candidates );
	}

	private static function add_candidate( array &$bucket, array $row, $score, $reason ) {
		$id = (int) $row['id'];
		if ( isset( $bucket[ $id ] ) ) {
			$bucket[ $id ]['score']    = max( $bucket[ $id ]['score'], $score );
			$bucket[ $id ]['reasons'][] = $reason;
			$bucket[ $id ]['reasons']   = array_values( array_unique( $bucket[ $id ]['reasons'] ) );
			return;
		}
		$bucket[ $id ] = array(
			'customer' => $row,
			'score'    => (int) $score,
			'reasons'  => array( $reason ),
		);
	}

	public static function create( array $data ) {
		global $wpdb;

		$now = g2a_crm_now();
		$row = self::sanitize( $data );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );

		G2A_CRM_Audit_Log::record( 'customer.create', 'customer', $id, null, $new );

		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );

		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'customer.update', 'customer', (int) $id, $diff['old'], $diff['new'] );
		}

		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );

		G2A_CRM_Audit_Log::record( 'customer.delete', 'customer', (int) $id, $existing, null );

		return true;
	}

	private static function safe_order_column( $column ) {
		$allowed = array( 'id', 'first_name', 'last_name', 'email', 'created_at', 'updated_at', 'last_activity_at' );
		return in_array( $column, $allowed, true ) ? $column : 'updated_at';
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();

		if ( array_key_exists( 'wp_user_id', $data ) ) {
			$row['wp_user_id'] = $data['wp_user_id'] ? (int) $data['wp_user_id'] : null;
		}
		if ( array_key_exists( 'first_name', $data ) ) {
			$row['first_name'] = sanitize_text_field( (string) $data['first_name'] );
		}
		if ( array_key_exists( 'last_name', $data ) ) {
			$row['last_name'] = sanitize_text_field( (string) $data['last_name'] );
		}
		if ( array_key_exists( 'email', $data ) ) {
			$row['email'] = $data['email'] ? sanitize_email( (string) $data['email'] ) : '';
		}
		if ( array_key_exists( 'phone', $data ) ) {
			$row['phone'] = g2a_crm_sanitize_phone( (string) $data['phone'] );
		}
		foreach ( array( 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'source' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$row[ $field ] = sanitize_text_field( (string) $data[ $field ] );
			}
		}
		if ( array_key_exists( 'customer_type', $data ) ) {
			$row['customer_type'] = g2a_crm_allowed_status( $data['customer_type'], self::TYPES, $existing['customer_type'] ?? 'walk_in' );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'active' );
		}
		if ( array_key_exists( 'preferred_contact', $data ) ) {
			$row['preferred_contact'] = g2a_crm_allowed_status( $data['preferred_contact'], self::CONTACT_METHODS, $existing['preferred_contact'] ?? 'email' );
		}
		if ( array_key_exists( 'waiver_status', $data ) ) {
			$row['waiver_status'] = g2a_crm_allowed_status( $data['waiver_status'], self::WAIVER_STATUSES, $existing['waiver_status'] ?? 'not_submitted' );
		}
		if ( array_key_exists( 'membership_status', $data ) ) {
			$row['membership_status'] = g2a_crm_allowed_status( $data['membership_status'], self::MEMBERSHIP_STATUSES, $existing['membership_status'] ?? 'none' );
		}
		if ( array_key_exists( 'date_of_birth', $data ) ) {
			$dob = sanitize_text_field( (string) $data['date_of_birth'] );
			$row['date_of_birth'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dob ) ? $dob : null;
		}
		if ( array_key_exists( 'notes_summary', $data ) ) {
			$row['notes_summary'] = wp_kses_post( (string) $data['notes_summary'] );
		}
		if ( array_key_exists( 'email_opt_in', $data ) ) {
			$row['email_opt_in'] = ! empty( $data['email_opt_in'] ) ? 1 : 0;
			if ( 0 === $row['email_opt_in'] && empty( $existing['email_opt_out_at'] ) ) {
				$row['email_opt_out_at'] = g2a_crm_now();
			} elseif ( 1 === $row['email_opt_in'] ) {
				$row['email_opt_out_at'] = null;
			}
		}
		if ( array_key_exists( 'sms_opt_in', $data ) ) {
			$row['sms_opt_in'] = ! empty( $data['sms_opt_in'] ) ? 1 : 0;
			if ( 0 === $row['sms_opt_in'] && empty( $existing['sms_opt_out_at'] ) ) {
				$row['sms_opt_out_at'] = g2a_crm_now();
			} elseif ( 1 === $row['sms_opt_in'] ) {
				$row['sms_opt_out_at'] = null;
			}
		}

		return $row;
	}
}
