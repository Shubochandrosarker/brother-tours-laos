<?php
/**
 * Waivers data access layer.
 *
 * Stores signed-waiver records and the path to the generated print view.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Waivers_Repository {

	const TYPES    = array( 'range', 'class', 'minor', 'event' );
	const STATUSES = array( 'submitted', 'verified', 'expired', 'needs_update', 'rejected', 'archived' );

	public static function table() {
		return g2a_crm_table( 'waivers' );
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
	 * Returns true if the customer has at least one verified, unexpired waiver.
	 * Used by the booking check-in gate.
	 */
	public static function has_valid_waiver( $customer_id ) {
		global $wpdb;
		$now = g2a_crm_now();
		$row = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE customer_id = %d AND status IN ("submitted","verified") AND (expires_at IS NULL OR expires_at > %s) ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $customer_id,
				$now
			)
		);
		return (bool) $row;
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
		if ( ! empty( $args['needs_verification'] ) ) {
			$where[] = 'status = "submitted"';
		}
		if ( ! empty( $args['expiring_within_days'] ) ) {
			$threshold  = gmdate( 'Y-m-d H:i:s', strtotime( '+' . (int) $args['expiring_within_days'] . ' days' ) );
			$where[]    = 'expires_at IS NOT NULL AND expires_at <= %s AND status IN ("submitted","verified")';
			$prepared[] = $threshold;
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
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
	 * Record a signed waiver. Called by the kiosk endpoint and by staff-assisted flow.
	 *
	 * @param array  $data        Customer + waiver fields.
	 * @param array  $signed_data Captured signature payload — typed name + base64 PNG.
	 * @param string $source      'kiosk' | 'staff' | 'web'.
	 */
	public static function create( array $data, array $signed_data, $source = 'staff' ) {
		global $wpdb;

		$customer_id = (int) ( $data['customer_id'] ?? 0 );
		if ( ! $customer_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Customer is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$customer = G2A_CRM_Customers_Repository::find( $customer_id );
		if ( ! $customer ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		$waiver_type = g2a_crm_allowed_status( $data['waiver_type'] ?? 'range', self::TYPES, 'range' );

		$signed_at  = g2a_crm_now();
		$expiry_days = (int) G2A_CRM_Settings::get( 'waiver_expiry_days', 365 );
		$expires_at = $expiry_days > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) ) : null;

		$typed_name      = sanitize_text_field( (string) ( $signed_data['typed_name'] ?? '' ) );
		$signature_image = isset( $signed_data['signature_image'] ) ? (string) $signed_data['signature_image'] : '';

		if ( '' === $typed_name && '' === $signature_image ) {
			return new WP_Error( 'g2a_crm_validation', __( 'A typed name or signature is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$hash_basis = $customer_id . '|' . $signed_at . '|' . $typed_name . '|' . wp_generate_password( 16, false );
		$signature_hash = hash( 'sha256', $hash_basis );

		$device_info = wp_json_encode(
			array(
				'source'          => sanitize_key( $source ),
				'user_agent'      => g2a_crm_user_agent(),
				'typed_name'      => $typed_name,
				'signature_present' => '' !== $signature_image,
			)
		);

		$row = array(
			'customer_id'    => $customer_id,
			'waiver_type'    => $waiver_type,
			'status'         => 'submitted',
			'signed_at'      => $signed_at,
			'expires_at'     => $expires_at,
			'signature_hash' => $signature_hash,
			'ip_address'     => g2a_crm_request_ip(),
			'device_info'    => $device_info,
			'created_at'     => $signed_at,
			'updated_at'     => $signed_at,
		);

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id = (int) $wpdb->insert_id;

		// Store the signature image off-table so blobs don't bloat queries.
		$signature_path = '';
		if ( '' !== $signature_image ) {
			$signature_path = self::store_signature_image( $id, $signature_image );
		}

		$pdf_url = G2A_CRM_Waiver_View_Service::print_view_url( $id );

		$wpdb->update(
			self::table(),
			array(
				'pdf_url' => $pdf_url,
				'device_info' => wp_json_encode(
					array_merge(
						json_decode( $device_info, true ) ?: array(),
						array( 'signature_path' => $signature_path )
					)
				),
			),
			array( 'id' => $id )
		);

		$new = self::find( $id );

		// Sync the customer's waiver_status flag and last_activity_at.
		$wpdb->update(
			g2a_crm_table( 'customers' ),
			array(
				'waiver_status'    => 'submitted',
				'last_activity_at' => $signed_at,
			),
			array( 'id' => $customer_id )
		);

		G2A_CRM_Audit_Log::record( 'waiver.create', 'waiver', $id, null, $new );

		return $new;
	}

	public static function verify( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Waiver not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$now = g2a_crm_now();
		$wpdb->update(
			self::table(),
			array(
				'status'      => 'verified',
				'verified_by' => get_current_user_id() ?: null,
				'verified_at' => $now,
				'updated_at'  => $now,
			),
			array( 'id' => (int) $id )
		);
		$wpdb->update(
			g2a_crm_table( 'customers' ),
			array( 'waiver_status' => 'verified' ),
			array( 'id' => (int) $existing['customer_id'] )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'waiver.verify', 'waiver', (int) $id, $existing, $new );
		return $new;
	}

	public static function reject( $id, $reason = '' ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Waiver not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'rejected',
				'updated_at' => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'waiver.reject', 'waiver', (int) $id, $existing, $new, $reason );
		return $new;
	}

	/**
	 * Mark expired waivers in bulk. Runs from the daily cron.
	 */
	public static function expire_due() {
		global $wpdb;
		$now = g2a_crm_now();
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET status = "expired", updated_at = %s WHERE status IN ("submitted","verified") AND expires_at IS NOT NULL AND expires_at <= %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$now,
				$now
			)
		);
		if ( $affected > 0 ) {
			G2A_CRM_Audit_Log::record( 'waiver.expire_batch', 'waiver', 0, null, array( 'expired_count' => (int) $affected ) );
		}
		return (int) $affected;
	}

	private static function store_signature_image( $waiver_id, $data_url ) {
		if ( 0 !== strpos( $data_url, 'data:image/png;base64,' ) ) {
			return '';
		}
		$base64 = substr( $data_url, strlen( 'data:image/png;base64,' ) );
		$binary = base64_decode( $base64, true );
		if ( ! $binary ) {
			return '';
		}
		$upload  = wp_upload_dir();
		$dir     = trailingslashit( $upload['basedir'] ) . 'g2a-crm/waivers';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$file = $dir . "/waiver-{$waiver_id}-" . wp_generate_password( 8, false ) . '.png';
		if ( false === file_put_contents( $file, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			return '';
		}
		// Best-effort: deny direct HTTP access via .htaccess if Apache.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "deny from all\n" ); // phpcs:ignore
		}
		return $file;
	}
}
