<?php
/**
 * Tasks data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Tasks_Repository {

	const STATUSES   = array( 'open', 'in_progress', 'waiting', 'completed', 'cancelled', 'overdue' );
	const PRIORITIES = array( 'low', 'normal', 'high', 'urgent' );
	const RELATED    = array( 'customer', 'lead', 'booking', 'class', 'membership', 'waiver', 'general' );

	public static function table() {
		return g2a_crm_table( 'tasks' );
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

		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['assigned_to'] ) ) {
			$where[]    = 'assigned_to = %d';
			$prepared[] = (int) $args['assigned_to'];
		}
		if ( ! empty( $args['related_type'] ) ) {
			$where[]    = 'related_type = %s';
			$prepared[] = sanitize_key( $args['related_type'] );
		}
		if ( ! empty( $args['related_id'] ) ) {
			$where[]    = 'related_id = %d';
			$prepared[] = (int) $args['related_id'];
		}
		if ( ! empty( $args['mine'] ) ) {
			$where[]    = 'assigned_to = %d';
			$prepared[] = get_current_user_id();
		}
		if ( ! empty( $args['overdue'] ) ) {
			$where[]    = 'status NOT IN ("completed","cancelled") AND due_at IS NOT NULL AND due_at < %s';
			$prepared[] = g2a_crm_now();
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY (due_at IS NULL), due_at ASC, id DESC LIMIT %d OFFSET %d",
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
		$row['created_by'] = get_current_user_id() ?: null;
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		if ( empty( $row['title'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Task title is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'task.create', 'task', $id, null, $new );
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Task not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'task.update', 'task', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function complete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Task not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$now = g2a_crm_now();
		$wpdb->update(
			self::table(),
			array(
				'status'       => 'completed',
				'completed_at' => $now,
				'completed_by' => get_current_user_id() ?: null,
				'updated_at'   => $now,
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'task.complete', 'task', (int) $id, $existing, $new );
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Task not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'task.delete', 'task', (int) $id, $existing, null );
		return true;
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();
		if ( array_key_exists( 'title', $data ) ) {
			$row['title'] = sanitize_text_field( (string) $data['title'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$row['description'] = $data['description'] ? wp_kses_post( (string) $data['description'] ) : null;
		}
		if ( array_key_exists( 'related_type', $data ) ) {
			$row['related_type'] = g2a_crm_allowed_status( $data['related_type'], self::RELATED, $existing['related_type'] ?? 'general' );
		}
		if ( array_key_exists( 'related_id', $data ) ) {
			$row['related_id'] = (int) $data['related_id'];
		}
		if ( array_key_exists( 'assigned_to', $data ) ) {
			$row['assigned_to'] = $data['assigned_to'] ? (int) $data['assigned_to'] : null;
		}
		if ( array_key_exists( 'priority', $data ) ) {
			$row['priority'] = g2a_crm_allowed_status( $data['priority'], self::PRIORITIES, $existing['priority'] ?? 'normal' );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'open' );
		}
		if ( array_key_exists( 'due_at', $data ) ) {
			if ( ! $data['due_at'] ) {
				$row['due_at'] = null;
			} else {
				$ts = strtotime( (string) $data['due_at'] );
				$row['due_at'] = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
			}
		}
		return $row;
	}
}
