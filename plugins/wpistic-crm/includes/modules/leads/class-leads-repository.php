<?php
/**
 * Leads data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Leads_Repository {

	const STATUSES = array( 'new', 'contacted', 'qualified', 'waiting', 'appointment_booked', 'converted', 'lost', 'not_a_fit', 'archived' );

	const INTEREST_TYPES = array( 'range_visit', 'membership', 'training', 'private_lesson', 'retail', 'ffl_transfer', 'group_event', 'general' );

	public static function table() {
		return g2a_crm_table( 'leads' );
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

		if ( ! empty( $args['search'] ) ) {
			$like       = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]    = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['interest_type'] ) ) {
			$where[]    = 'interest_type = %s';
			$prepared[] = sanitize_key( $args['interest_type'] );
		}
		if ( ! empty( $args['assigned_to'] ) ) {
			$where[]    = 'assigned_to = %d';
			$prepared[] = (int) $args['assigned_to'];
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

		G2A_CRM_Audit_Log::record( 'lead.create', 'lead', $id, null, $new );
		self::activity( $id, 'created', __( 'Lead created.', 'guns2ammo-crm' ) );

		// Run customer match + auto-assign + follow-up task + assignee notify.
		// Returns the (possibly updated) lead row.
		if ( class_exists( 'G2A_CRM_Lead_Auto_Assigner' ) ) {
			$new = G2A_CRM_Lead_Auto_Assigner::process_new_lead( $new );
		}

		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );

		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'lead.update', 'lead', (int) $id, $diff['old'], $diff['new'] );
		}
		if ( isset( $diff['new']['status'] ) ) {
			self::activity(
				(int) $id,
				'status_change',
				sprintf(
					/* translators: 1: old status, 2: new status */
					__( 'Status: %1$s → %2$s', 'guns2ammo-crm' ),
					$diff['old']['status'] ?? '',
					$diff['new']['status']
				)
			);
		}

		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'lead.delete', 'lead', (int) $id, $existing, null );
		return true;
	}

	public static function convert( $id, array $customer_overrides = array() ) {
		$lead = self::find( $id );
		if ( ! $lead ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Lead not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( $lead['customer_id'] ) {
			return new WP_Error( 'g2a_crm_already_converted', __( 'Lead already converted.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		// Try to find or create a customer.
		$existing_customer = G2A_CRM_Customers_Repository::find_by_email( $lead['email'] );

		if ( $existing_customer ) {
			$customer = $existing_customer;
		} else {
			$parts        = preg_split( '/\s+/', trim( (string) $lead['name'] ), 2 );
			$customer_data = array_merge(
				array(
					'first_name' => $parts[0] ?? '',
					'last_name'  => $parts[1] ?? '',
					'email'      => $lead['email'],
					'phone'      => $lead['phone'],
					'source'     => $lead['source'],
					'customer_type' => 'lead',
				),
				$customer_overrides
			);
			$customer = G2A_CRM_Customers_Repository::create( $customer_data );
			if ( is_wp_error( $customer ) ) {
				return $customer;
			}
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'customer_id' => (int) $customer['id'],
				'status'      => 'converted',
				'updated_at'  => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);

		G2A_CRM_Audit_Log::record( 'lead.convert', 'lead', (int) $id, $lead, array( 'customer_id' => (int) $customer['id'] ) );
		self::activity( (int) $id, 'converted', sprintf( /* translators: %d: customer id */ __( 'Converted to customer #%d', 'guns2ammo-crm' ), (int) $customer['id'] ) );

		return array(
			'lead'     => self::find( $id ),
			'customer' => $customer,
		);
	}

	public static function activity( $lead_id, $type, $summary, $body = null ) {
		global $wpdb;
		$wpdb->insert(
			g2a_crm_table( 'lead_activities' ),
			array(
				'lead_id'       => (int) $lead_id,
				'actor_id'      => get_current_user_id() ?: null,
				'activity_type' => sanitize_key( $type ),
				'summary'       => sanitize_text_field( $summary ),
				'body'          => null !== $body ? wp_kses_post( $body ) : null,
				'created_at'    => g2a_crm_now(),
			)
		);
	}

	public static function activities( $lead_id, $limit = 50 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . g2a_crm_table( 'lead_activities' ) . ' WHERE lead_id = %d ORDER BY id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $lead_id,
				max( 1, min( 200, (int) $limit ) )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();
		if ( array_key_exists( 'customer_id', $data ) ) {
			$row['customer_id'] = $data['customer_id'] ? (int) $data['customer_id'] : null;
		}
		if ( array_key_exists( 'name', $data ) ) {
			$row['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'email', $data ) ) {
			$row['email'] = $data['email'] ? sanitize_email( (string) $data['email'] ) : '';
		}
		if ( array_key_exists( 'phone', $data ) ) {
			$row['phone'] = g2a_crm_sanitize_phone( (string) $data['phone'] );
		}
		if ( array_key_exists( 'interest_type', $data ) ) {
			$row['interest_type'] = g2a_crm_allowed_status( $data['interest_type'], self::INTEREST_TYPES, $existing['interest_type'] ?? 'general' );
		}
		if ( array_key_exists( 'source', $data ) ) {
			$row['source'] = sanitize_text_field( (string) $data['source'] );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'new' );
		}
		if ( array_key_exists( 'assigned_to', $data ) ) {
			$row['assigned_to'] = $data['assigned_to'] ? (int) $data['assigned_to'] : null;
		}
		if ( array_key_exists( 'lead_score', $data ) ) {
			$row['lead_score'] = (int) $data['lead_score'];
		}
		if ( array_key_exists( 'next_follow_up_at', $data ) ) {
			$row['next_follow_up_at'] = self::sanitize_datetime( $data['next_follow_up_at'] );
		}
		if ( array_key_exists( 'last_contacted_at', $data ) ) {
			$row['last_contacted_at'] = self::sanitize_datetime( $data['last_contacted_at'] );
		}
		if ( array_key_exists( 'conversion_value', $data ) ) {
			$row['conversion_value'] = (float) $data['conversion_value'];
		}
		if ( array_key_exists( 'lost_reason', $data ) ) {
			$row['lost_reason'] = $data['lost_reason'] ? sanitize_textarea_field( (string) $data['lost_reason'] ) : null;
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$row['notes'] = $data['notes'] ? wp_kses_post( (string) $data['notes'] ) : null;
		}
		return $row;
	}

	private static function sanitize_datetime( $value ) {
		if ( ! $value ) {
			return null;
		}
		$value = sanitize_text_field( (string) $value );
		$ts    = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
