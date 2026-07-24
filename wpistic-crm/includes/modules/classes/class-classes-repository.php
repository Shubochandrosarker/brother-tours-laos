<?php
/**
 * Classes data access layer.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Classes_Repository {

	const TYPES    = array( 'ccw', 'safety', 'intro', 'private', 'group', 'orientation', 'event' );
	const STATUSES = array( 'scheduled', 'in_progress', 'completed', 'cancelled' );

	public static function table() {
		return g2a_crm_table( 'classes' );
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
		if ( ! empty( $args['class_type'] ) ) {
			$where[]    = 'class_type = %s';
			$prepared[] = sanitize_key( $args['class_type'] );
		}
		if ( ! empty( $args['instructor_id'] ) ) {
			$where[]    = 'instructor_id = %d';
			$prepared[] = (int) $args['instructor_id'];
		}
		if ( ! empty( $args['mine_as_instructor'] ) ) {
			$where[]    = 'instructor_id = %d';
			$prepared[] = get_current_user_id();
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]    = 'start_time >= %s';
			$prepared[] = self::sanitize_dt( $args['from'] );
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]    = 'start_time <= %s';
			$prepared[] = self::sanitize_dt( $args['to'] );
		}
		if ( ! empty( $args['upcoming'] ) ) {
			$where[]    = 'start_time >= %s';
			$prepared[] = g2a_crm_now();
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

		// Hydrate enrollment counts.
		if ( $rows ) {
			$ids          = array_map( static fn( $r ) => (int) $r['id'], $rows );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$counts       = $wpdb->get_results(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT class_id, COUNT(*) AS cnt FROM ' . g2a_crm_table( 'class_students' ) . ' WHERE class_id IN (' . $placeholders . ') AND status NOT IN ("cancelled") GROUP BY class_id',
					$ids
				),
				ARRAY_A
			);
			$by_id = array();
			foreach ( $counts ?: array() as $c ) {
				$by_id[ (int) $c['class_id'] ] = (int) $c['cnt'];
			}
			foreach ( $rows as &$row ) {
				$row['enrolled'] = $by_id[ (int) $row['id'] ] ?? 0;
				$row['seats_left'] = (int) $row['capacity'] - $row['enrolled'];
			}
		}

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	public static function create( array $data ) {
		global $wpdb;
		$row = self::sanitize( $data );
		if ( empty( $row['name'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Class name is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['start_time'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Class start time is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
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
		G2A_CRM_Audit_Log::record( 'class.create', 'class', $id, null, $new );
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$patch               = self::sanitize( $data, $existing );
		$patch['updated_at'] = g2a_crm_now();

		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'class.update', 'class', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function cancel( $id, $reason = '' ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'cancelled',
				'updated_at' => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
		// Also cancel each student enrollment so dashboards stay clean.
		$wpdb->update(
			g2a_crm_table( 'class_students' ),
			array(
				'status'     => 'cancelled',
				'updated_at' => g2a_crm_now(),
			),
			array( 'class_id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'class.cancel', 'class', (int) $id, $existing, $new, $reason );
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Class not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( g2a_crm_table( 'class_students' ), array( 'class_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'class.delete', 'class', (int) $id, $existing, null );
		return true;
	}

	private static function sanitize( array $data, array $existing = array() ) {
		$row = array();
		if ( array_key_exists( 'name', $data ) ) {
			$row['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'class_type', $data ) ) {
			$row['class_type'] = g2a_crm_allowed_status( $data['class_type'], self::TYPES, $existing['class_type'] ?? 'safety' );
		}
		if ( array_key_exists( 'instructor_id', $data ) ) {
			$row['instructor_id'] = $data['instructor_id'] ? (int) $data['instructor_id'] : null;
		}
		if ( array_key_exists( 'start_time', $data ) ) {
			$row['start_time'] = self::sanitize_dt( $data['start_time'] );
		}
		if ( array_key_exists( 'end_time', $data ) ) {
			$row['end_time'] = self::sanitize_dt( $data['end_time'] );
		}
		if ( array_key_exists( 'capacity', $data ) ) {
			$row['capacity'] = max( 0, (int) $data['capacity'] );
		}
		if ( array_key_exists( 'price', $data ) ) {
			$row['price'] = (float) $data['price'];
		}
		if ( array_key_exists( 'required_waiver', $data ) ) {
			$row['required_waiver'] = ! empty( $data['required_waiver'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = g2a_crm_allowed_status( $data['status'], self::STATUSES, $existing['status'] ?? 'scheduled' );
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
