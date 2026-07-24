<?php
/**
 * Webhook delivery attempts. One row per (webhook x event), retried on failure
 * with exponential backoff up to MAX_ATTEMPTS.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Webhook_Deliveries_Repository {

	const STATUSES = array( 'queued', 'retrying', 'sent', 'failed', 'cancelled' );

	const MAX_ATTEMPTS = 6;

	/** Exponential backoff schedule in seconds: 1m, 5m, 30m, 2h, 12h, 24h. */
	const BACKOFF = array( 60, 300, 1800, 7200, 43200, 86400 );

	public static function table() {
		return g2a_crm_table( 'webhook_deliveries' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function list_for_webhook( $webhook_id, $limit = 25 ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE webhook_id = %d ORDER BY id DESC LIMIT %d",
				(int) $webhook_id,
				max( 1, min( 200, (int) $limit ) )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Pick up to N deliveries that are ready to send.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function due( $limit = 25 ) {
		global $wpdb;
		$table = self::table();
		$now   = g2a_crm_now();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE status IN ('queued','retrying') AND (next_attempt_at IS NULL OR next_attempt_at <= %s) ORDER BY id ASC LIMIT %d",
				$now,
				max( 1, min( 100, (int) $limit ) )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Queue a new delivery (status=queued, attempt_count=0, next_attempt_at=now).
	 */
	public static function enqueue( $webhook_id, $event, $target_url, array $payload ) {
		global $wpdb;
		$now = g2a_crm_now();
		$row = array(
			'webhook_id'      => (int) $webhook_id,
			'event'           => sanitize_text_field( $event ),
			'target_url'      => esc_url_raw( $target_url ),
			'payload'         => wp_json_encode( $payload ),
			'status'          => 'queued',
			'attempt_count'   => 0,
			'next_attempt_at' => $now,
			'created_at'      => $now,
		);
		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		return (int) $wpdb->insert_id;
	}

	public static function mark_sent( $id, $code, $body ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'        => 'sent',
				'response_code' => (int) $code,
				'response_body' => self::truncate_body( $body ),
				'delivered_at'  => g2a_crm_now(),
				'last_error'    => null,
			),
			array( 'id' => (int) $id )
		);
	}

	public static function mark_failure( $id, $code, $body, $error ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return;
		}
		$attempt = (int) $existing['attempt_count'] + 1;
		$is_final = $attempt >= self::MAX_ATTEMPTS;
		$status   = $is_final ? 'failed' : 'retrying';

		$next_at = null;
		if ( ! $is_final ) {
			$backoff_idx = min( $attempt - 1, count( self::BACKOFF ) - 1 );
			$next_at     = gmdate( 'Y-m-d H:i:s', time() + self::BACKOFF[ $backoff_idx ] );
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'          => $status,
				'response_code'   => $code ? (int) $code : null,
				'response_body'   => self::truncate_body( $body ),
				'last_error'      => $error ? substr( (string) $error, 0, 1000 ) : null,
				'attempt_count'   => $attempt,
				'next_attempt_at' => $next_at,
			),
			array( 'id' => (int) $id )
		);
	}

	public static function set_attempt_started( $id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'attempt_count' => $wpdb->get_var( $wpdb->prepare( 'SELECT attempt_count + 1 FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			array( 'id' => (int) $id )
		);
	}

	private static function truncate_body( $body ) {
		if ( null === $body || '' === $body ) {
			return null;
		}
		return substr( (string) $body, 0, 4000 );
	}
}
