<?php
/**
 * Messages data access layer.
 *
 * Outbound messages flow: queued → sending → sent (or failed). The dispatcher
 * cron picks up queued rows and runs them through the renderer + sender.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Messages_Repository {

	const CHANNELS  = array( 'email', 'sms', 'reminder', 'internal' );
	const STATUSES  = array( 'queued', 'sending', 'sent', 'failed', 'cancelled', 'skipped' );
	const DIRECTIONS = array( 'outbound', 'inbound' );

	const MAX_ATTEMPTS = 5;

	public static function table() {
		return g2a_crm_table( 'messages' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_by_dedup_key( $key ) {
		if ( '' === (string) $key ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE dedup_key = %s ORDER BY id DESC LIMIT 1', (string) $key ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]    = 'customer_id = %d';
			$prepared[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['channel'] ) ) {
			$where[]    = 'channel = %s';
			$prepared[] = sanitize_key( $args['channel'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like       = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]    = '(subject LIKE %s OR rendered_subject LIKE %s OR to_address LIKE %s)';
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
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
	 * Queue a new outbound message. If a dedup_key is given and a row already
	 * exists for it, returns the existing row (idempotent).
	 *
	 * @param array $data
	 * @return array|WP_Error
	 */
	public static function queue( array $data ) {
		global $wpdb;

		$dedup_key = isset( $data['dedup_key'] ) ? sanitize_text_field( (string) $data['dedup_key'] ) : '';
		if ( '' !== $dedup_key ) {
			$existing = self::find_by_dedup_key( $dedup_key );
			if ( $existing ) {
				return $existing;
			}
		}

		$channel = g2a_crm_allowed_status( $data['channel'] ?? 'email', self::CHANNELS, 'email' );

		$row = array(
			'customer_id'         => isset( $data['customer_id'] ) && $data['customer_id'] ? (int) $data['customer_id'] : null,
			'lead_id'             => isset( $data['lead_id'] ) && $data['lead_id'] ? (int) $data['lead_id'] : null,
			'channel'             => $channel,
			'direction'           => 'outbound',
			'template_id'         => isset( $data['template_id'] ) && $data['template_id'] ? (int) $data['template_id'] : null,
			'subject'             => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ),
			'body'                => isset( $data['body'] ) ? wp_kses_post( (string) $data['body'] ) : '',
			'to_address'          => sanitize_text_field( (string) ( $data['to_address'] ?? '' ) ),
			'status'              => 'queued',
			'dedup_key'           => $dedup_key,
			'next_attempt_at'     => isset( $data['next_attempt_at'] ) ? sanitize_text_field( (string) $data['next_attempt_at'] ) : g2a_crm_now(),
			'sent_by'             => get_current_user_id() ?: null,
			'created_at'          => g2a_crm_now(),
		);

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'message.queued', 'message', $id, null, array(
			'channel'   => $new['channel'],
			'template'  => $new['template_id'],
			'dedup_key' => $new['dedup_key'],
		) );
		return $new;
	}

	/**
	 * Atomically claim queued rows for sending. Returns the rows claimed.
	 *
	 * Sets status=sending so concurrent dispatchers don't double-send.
	 */
	public static function claim_batch( $limit = 25 ) {
		global $wpdb;
		$table = self::table();
		$now   = g2a_crm_now();
		$limit = max( 1, min( 100, (int) $limit ) );

		// Two-step claim: select candidate IDs, then update them to 'sending' atomically.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= %s) ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$limit
			)
		);
		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$table} SET status = 'sending', attempt_count = attempt_count + 1 WHERE id IN ({$placeholders}) AND status = 'queued'",
				$ids
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE id IN ({$placeholders}) AND status = 'sending'",
				$ids
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public static function mark_sent( $id, $provider, $provider_message_id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'              => 'sent',
				'provider'            => sanitize_text_field( $provider ),
				'provider_message_id' => sanitize_text_field( $provider_message_id ),
				'sent_at'             => g2a_crm_now(),
				'error_text'          => null,
			),
			array( 'id' => (int) $id )
		);
	}

	public static function mark_failed( $id, $error_text, $retryable = false ) {
		global $wpdb;
		$row = self::find( $id );
		if ( ! $row ) {
			return;
		}
		$next_status = 'failed';
		$next_at     = null;
		if ( $retryable && (int) $row['attempt_count'] < self::MAX_ATTEMPTS ) {
			$next_status = 'queued';
			$backoff_min = pow( 2, (int) $row['attempt_count'] ); // 1, 2, 4, 8, 16
			$next_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$backoff_min} minutes" ) );
		}
		$wpdb->update(
			self::table(),
			array(
				'status'          => $next_status,
				'error_text'      => substr( (string) $error_text, 0, 2000 ),
				'next_attempt_at' => $next_at,
			),
			array( 'id' => (int) $id )
		);
		G2A_CRM_Audit_Log::record( 'message.failed', 'message', (int) $id, array( 'attempt' => (int) $row['attempt_count'] ), array( 'status' => $next_status, 'error' => $error_text ) );
	}

	public static function cancel( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Message not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $existing['status'], array( 'queued', 'failed' ), true ) ) {
			return new WP_Error( 'g2a_crm_invalid_state', __( 'Only queued or failed messages can be cancelled.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => 'cancelled' ), array( 'id' => (int) $id ) );
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'message.cancel', 'message', (int) $id, $existing, $new );
		return $new;
	}

	public static function retry( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Message not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $existing['status'], array( 'failed', 'cancelled' ), true ) ) {
			return new WP_Error( 'g2a_crm_invalid_state', __( 'Only failed or cancelled messages can be retried.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'          => 'queued',
				'next_attempt_at' => g2a_crm_now(),
				'error_text'      => null,
				'attempt_count'   => 0,
			),
			array( 'id' => (int) $id )
		);
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'message.retry', 'message', (int) $id, $existing, $new );
		return $new;
	}

	public static function stats() {
		global $wpdb;
		$table = self::table();
		$now   = g2a_crm_now();
		$today_start = gmdate( 'Y-m-d 00:00:00' );

		return array(
			'queued_total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('queued','sending')" ),
			'sent_today'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND sent_at >= %s", $today_start ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'failed_24h'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND created_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
