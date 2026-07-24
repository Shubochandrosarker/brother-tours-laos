<?php
/**
 * CSV export service.
 *
 * Streams rows so big exports don't blow up memory. Each exporter is a
 * generator-style closure that yields rows for one entity type, plus a static
 * column list.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_CSV_Exporter {

	const EXPORTABLE = array( 'customers', 'leads', 'bookings', 'memberships', 'messages', 'waivers' );

	public static function is_exportable( $type ) {
		return in_array( (string) $type, self::EXPORTABLE, true );
	}

	/**
	 * Stream a CSV response to STDOUT and exit. Caller must verify perms first.
	 *
	 * @param string $type   One of self::EXPORTABLE.
	 * @param array  $params Optional filter params (from, to, status, ...).
	 */
	public static function stream( $type, array $params = array() ) {
		if ( ! self::is_exportable( $type ) ) {
			status_header( 400 );
			exit( 'Unknown export type' );
		}

		// Flush WP buffers — we're sending CSV, not HTML.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$filename = sprintf( 'g2a-crm-%s-%s.csv', $type, gmdate( 'Y-m-d-His' ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Cache-Control: private, no-cache, must-revalidate' );

		$fh = fopen( 'php://output', 'w' );

		$method = 'export_' . $type;
		self::$method( $fh, $params );

		fclose( $fh );
		exit;
	}

	private static function row( $fh, array $row ) {
		fputcsv( $fh, array_map( static fn( $v ) => null === $v ? '' : (string) $v, $row ) );
	}

	private static function range_clause( $params, $column, &$prepared ) {
		global $wpdb;
		$where = '';
		if ( ! empty( $params['from'] ) ) {
			$ts = strtotime( (string) $params['from'] );
			if ( $ts ) {
				$where     .= $wpdb->prepare( " AND {$column} >= %s", gmdate( 'Y-m-d 00:00:00', $ts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		if ( ! empty( $params['to'] ) ) {
			$ts = strtotime( (string) $params['to'] );
			if ( $ts ) {
				$where .= $wpdb->prepare( " AND {$column} <= %s", gmdate( 'Y-m-d 23:59:59', $ts ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		return $where;
	}

	private static function export_customers( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'first_name', 'last_name', 'email', 'phone', 'customer_type', 'status', 'source', 'waiver_status', 'membership_status', 'email_opt_in', 'sms_opt_in', 'last_activity_at', 'created_at' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Customers_Repository::table();
		$where = self::range_clause( $params, 'created_at', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}

	private static function export_leads( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'customer_id', 'name', 'email', 'phone', 'interest_type', 'source', 'status', 'lead_score', 'next_follow_up_at', 'conversion_value', 'created_at' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Leads_Repository::table();
		$where = self::range_clause( $params, 'created_at', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}

	private static function export_bookings( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'customer_id', 'booking_type', 'resource_type', 'resource_id', 'start_time', 'end_time', 'status', 'payment_status', 'amount', 'staff_id', 'created_at' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Bookings_Repository::table();
		$where = self::range_clause( $params, 'start_time', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}

	private static function export_memberships( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'customer_id', 'plan_name', 'access_level', 'status', 'billing_status', 'monthly_amount', 'start_date', 'renewal_date', 'cancelled_at', 'cancellation_reason', 'created_at' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Memberships_Repository::table();
		$where = self::range_clause( $params, 'created_at', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}

	private static function export_messages( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'customer_id', 'channel', 'direction', 'rendered_subject', 'to_address', 'status', 'provider', 'attempt_count', 'sent_at', 'error_text', 'created_at' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Messages_Repository::table();
		$where = self::range_clause( $params, 'created_at', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}

	private static function export_waivers( $fh, $params ) {
		global $wpdb;
		$cols = array( 'id', 'customer_id', 'waiver_type', 'status', 'signed_at', 'expires_at', 'verified_by', 'verified_at', 'ip_address', 'signature_hash' );
		self::row( $fh, $cols );
		$table = G2A_CRM_Waivers_Repository::table();
		$where = self::range_clause( $params, 'signed_at', $_ );
		$rows = $wpdb->get_results( "SELECT " . implode( ',', $cols ) . " FROM {$table} WHERE 1=1 {$where} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows ?: array() as $row ) {
			self::row( $fh, array_map( static fn( $c ) => $row[ $c ] ?? '', $cols ) );
		}
	}
}
