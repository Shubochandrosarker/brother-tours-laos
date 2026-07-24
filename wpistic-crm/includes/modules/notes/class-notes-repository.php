<?php
/**
 * Notes data access layer.
 *
 * Notes are polymorphic — they attach to any record via (related_type, related_id).
 * Access control happens at the controller layer: viewing/editing a note follows
 * the underlying record's capability (e.g. customer notes require view_customers).
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Notes_Repository {

	const RELATED_TYPES = array( 'customer', 'lead', 'booking', 'class', 'membership', 'waiver' );

	public static function table() {
		return g2a_crm_table( 'notes' );
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

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['related_type'] ) ) {
			$where[]    = 'related_type = %s';
			$prepared[] = sanitize_key( $args['related_type'] );
		}
		if ( ! empty( $args['related_id'] ) ) {
			$where[]    = 'related_id = %d';
			$prepared[] = (int) $args['related_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( $count_sql, $prepared ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $prepared, array( $per_page, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	public static function create( array $data ) {
		$row = self::sanitize( $data );
		if ( empty( $row['body'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Note body is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['related_type'] ) || empty( $row['related_id'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'related_type and related_id are required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$now              = g2a_crm_now();
		$row['author_id'] = get_current_user_id() ?: null;
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record(
			'note.create',
			$row['related_type'],
			(int) $row['related_id'],
			null,
			array( 'note_id' => $id )
		);
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Note not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$patch = self::sanitize( $data );
		// related_type/related_id are immutable post-creation.
		unset( $patch['related_type'], $patch['related_id'] );
		if ( empty( $patch ) ) {
			return $existing;
		}
		$patch['updated_at'] = g2a_crm_now();

		global $wpdb;
		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record(
				'note.update',
				$existing['related_type'],
				(int) $existing['related_id'],
				$diff['old'],
				$diff['new']
			);
		}
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Note not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record(
			'note.delete',
			$existing['related_type'],
			(int) $existing['related_id'],
			$existing,
			null
		);
		return true;
	}

	private static function sanitize( array $data ) {
		$row = array();
		if ( array_key_exists( 'related_type', $data ) ) {
			$value = sanitize_key( (string) $data['related_type'] );
			$row['related_type'] = in_array( $value, self::RELATED_TYPES, true ) ? $value : '';
		}
		if ( array_key_exists( 'related_id', $data ) ) {
			$row['related_id'] = (int) $data['related_id'];
		}
		if ( array_key_exists( 'body', $data ) ) {
			$row['body'] = wp_kses_post( (string) $data['body'] );
		}
		if ( array_key_exists( 'is_internal', $data ) ) {
			$row['is_internal'] = $data['is_internal'] ? 1 : 0;
		}
		return $row;
	}
}
