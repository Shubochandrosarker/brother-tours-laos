<?php
/**
 * Integrations data access.
 *
 * Stores references from external systems (currently WooCommerce) to local
 * CRM entities. One row per (provider, external_type, external_id). The
 * payload column holds a JSON snapshot of the external record so we never
 * need to re-fetch from the source when rendering history.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Integrations_Repository {

	const PROVIDERS = array( 'woocommerce' );

	public static function table() {
		return g2a_crm_table( 'integrations' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ? self::decode( $row ) : null;
	}

	public static function find_external( $provider, $external_type, $external_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'SELECT * FROM ' . self::table() . ' WHERE provider = %s AND external_type = %s AND external_id = %s LIMIT 1',
				sanitize_key( $provider ),
				sanitize_key( $external_type ),
				(string) $external_id
			),
			ARRAY_A
		);
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * List with optional filters.
	 *
	 * @param array $args provider, external_type, local_type, local_id, page, per_page
	 * @return array{rows:array,total:int}
	 */
	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? min( 200, max( 1, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['provider'] ) ) {
			$where[]    = 'provider = %s';
			$prepared[] = sanitize_key( $args['provider'] );
		}
		if ( ! empty( $args['external_type'] ) ) {
			$where[]    = 'external_type = %s';
			$prepared[] = sanitize_key( $args['external_type'] );
		}
		if ( ! empty( $args['local_type'] ) ) {
			$where[]    = 'local_type = %s';
			$prepared[] = sanitize_key( $args['local_type'] );
		}
		if ( ! empty( $args['local_id'] ) ) {
			$where[]    = 'local_id = %d';
			$prepared[] = (int) $args['local_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( $count_sql, $prepared ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $prepared, array( $per_page, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$rows = array_map( array( __CLASS__, 'decode' ), $rows ?: array() );

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Upsert by (provider, external_type, external_id). Returns the row.
	 *
	 * @param array $data
	 * @return array|WP_Error
	 */
	public static function upsert( array $data ) {
		$row = self::sanitize( $data );
		foreach ( array( 'provider', 'external_type', 'external_id', 'local_type', 'local_id' ) as $required ) {
			if ( empty( $row[ $required ] ) ) {
				return new WP_Error( 'g2a_crm_validation', sprintf( '%s is required.', $required ), array( 'status' => 400 ) );
			}
		}

		global $wpdb;
		$existing = self::find_external( $row['provider'], $row['external_type'], $row['external_id'] );
		$now      = g2a_crm_now();

		if ( $existing ) {
			$patch = array(
				'local_type'     => $row['local_type'],
				'local_id'       => $row['local_id'],
				'payload'        => isset( $row['payload'] ) ? $row['payload'] : ( is_string( $existing['payload'] ) ? $existing['payload'] : wp_json_encode( $existing['payload'] ) ),
				'last_synced_at' => $now,
			);
			$wpdb->update( self::table(), $patch, array( 'id' => (int) $existing['id'] ) );
			return self::find( (int) $existing['id'] );
		}

		$row['last_synced_at'] = $now;
		$row['created_at']     = $now;
		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		return self::find( (int) $wpdb->insert_id );
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Aggregate stats grouped by provider.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results(
			"SELECT provider, external_type, COUNT(*) AS total, MAX(last_synced_at) AS last_synced_at
			 FROM {$table}
			 GROUP BY provider, external_type
			 ORDER BY provider, external_type",
			ARRAY_A
		);
		return $rows ?: array();
	}

	private static function sanitize( array $data ) {
		$row = array();
		if ( isset( $data['provider'] ) ) {
			$row['provider'] = sanitize_key( (string) $data['provider'] );
		}
		if ( isset( $data['external_type'] ) ) {
			$row['external_type'] = sanitize_key( (string) $data['external_type'] );
		}
		if ( isset( $data['external_id'] ) ) {
			$row['external_id'] = substr( sanitize_text_field( (string) $data['external_id'] ), 0, 190 );
		}
		if ( isset( $data['local_type'] ) ) {
			$row['local_type'] = sanitize_key( (string) $data['local_type'] );
		}
		if ( isset( $data['local_id'] ) ) {
			$row['local_id'] = (int) $data['local_id'];
		}
		if ( array_key_exists( 'payload', $data ) ) {
			$row['payload'] = is_string( $data['payload'] ) ? $data['payload'] : wp_json_encode( $data['payload'] );
		}
		return $row;
	}

	private static function decode( $row ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}
		if ( isset( $row['payload'] ) && is_string( $row['payload'] ) && '' !== $row['payload'] ) {
			$decoded = json_decode( $row['payload'], true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$row['payload'] = $decoded;
			}
		}
		return $row;
	}
}
