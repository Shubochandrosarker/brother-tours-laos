<?php
/**
 * Tags data access layer.
 *
 * Tags are a shared taxonomy. Right now they only attach to customers but the
 * schema and API are kept generic enough to extend to leads later.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Tags_Repository {

	public static function table() {
		return g2a_crm_table( 'tags' );
	}

	public static function pivot_table() {
		return g2a_crm_table( 'customer_tags' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_by_slug( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE slug = %s LIMIT 1', $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 100;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['search'] ) ) {
			$like        = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]     = '(name LIKE %s OR slug LIKE %s)';
			$prepared[] = $like;
			$prepared[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( $count_sql, $prepared ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC LIMIT %d OFFSET %d";
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
		if ( empty( $row['name'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Tag name is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['slug'] ) ) {
			$row['slug'] = sanitize_key( str_replace( ' ', '-', strtolower( $row['name'] ) ) );
		}
		if ( ! $row['slug'] ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Tag slug could not be generated.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$existing = self::find_by_slug( $row['slug'] );
		if ( $existing ) {
			return new WP_Error( 'g2a_crm_duplicate_tag', __( 'A tag with that slug already exists.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		global $wpdb;
		$row['created_at'] = g2a_crm_now();
		$result            = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'tag.create', 'tag', $id, null, $new );
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Tag not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$patch = self::sanitize( $data );
		if ( empty( $patch ) ) {
			return $existing;
		}

		if ( isset( $patch['slug'] ) && $patch['slug'] !== $existing['slug'] ) {
			$dup = self::find_by_slug( $patch['slug'] );
			if ( $dup && (int) $dup['id'] !== (int) $id ) {
				return new WP_Error( 'g2a_crm_duplicate_tag', __( 'A tag with that slug already exists.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
			}
		}

		global $wpdb;
		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'tag.update', 'tag', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Tag not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::pivot_table(), array( 'tag_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'tag.delete', 'tag', (int) $id, $existing, null );
		return true;
	}

	/**
	 * Return all tags currently attached to a customer.
	 *
	 * @param int $customer_id
	 * @return array
	 */
	public static function for_customer( $customer_id ) {
		global $wpdb;
		$tags  = self::table();
		$pivot = self::pivot_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT t.* FROM {$tags} t INNER JOIN {$pivot} p ON p.tag_id = t.id WHERE p.customer_id = %d ORDER BY t.name ASC",
				(int) $customer_id
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	public static function attach_to_customer( $customer_id, $tag_id ) {
		$customer_id = (int) $customer_id;
		$tag_id      = (int) $tag_id;
		if ( ! $customer_id || ! $tag_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Customer and tag IDs are required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( ! self::find( $tag_id ) ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Tag not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$pivot = self::pivot_table();
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$pivot} WHERE customer_id = %d AND tag_id = %d LIMIT 1", $customer_id, $tag_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( $exists ) {
			return self::for_customer( $customer_id );
		}

		$wpdb->insert(
			$pivot,
			array(
				'customer_id' => $customer_id,
				'tag_id'      => $tag_id,
				'created_at'  => g2a_crm_now(),
			),
			array( '%d', '%d', '%s' )
		);

		G2A_CRM_Audit_Log::record( 'customer.tag_attach', 'customer', $customer_id, null, array( 'tag_id' => $tag_id ) );
		return self::for_customer( $customer_id );
	}

	public static function detach_from_customer( $customer_id, $tag_id ) {
		global $wpdb;
		$wpdb->delete(
			self::pivot_table(),
			array(
				'customer_id' => (int) $customer_id,
				'tag_id'      => (int) $tag_id,
			),
			array( '%d', '%d' )
		);
		G2A_CRM_Audit_Log::record( 'customer.tag_detach', 'customer', (int) $customer_id, array( 'tag_id' => (int) $tag_id ), null );
		return self::for_customer( $customer_id );
	}

	private static function sanitize( array $data ) {
		$row = array();
		if ( array_key_exists( 'name', $data ) ) {
			$row['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'slug', $data ) ) {
			$row['slug'] = sanitize_key( (string) $data['slug'] );
		}
		if ( array_key_exists( 'color', $data ) ) {
			$color = sanitize_hex_color( (string) $data['color'] );
			$row['color'] = $color ? $color : '';
		}
		return $row;
	}
}
