<?php
/**
 * Webhooks data access.
 *
 * A webhook subscribes to a single event (e.g. "customer.create") OR to the
 * wildcard "*" to receive every event. The secret is server-generated on
 * create unless the caller supplies one; once set, it's only ever returned
 * masked (the controller handles masking on read).
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Webhooks_Repository {

	const EVENTS = array(
		'customer.create',
		'customer.update',
		'customer.delete',
		'customer.tag_attach',
		'customer.tag_detach',
		'lead.create',
		'lead.update',
		'lead.convert',
		'lead.delete',
		'booking.create',
		'booking.update',
		'booking.check_in',
		'booking.cancel',
		'booking.complete',
		'waiver.create',
		'waiver.verify',
		'waiver.reject',
		'membership.create',
		'membership.update',
		'membership.cancel',
		'membership.renew',
		'class.create',
		'class.cancel',
		'class.student_register',
		'task.create',
		'task.complete',
		'message.sent',
		'message.failed',
		'sensitive_record.create',
		'sensitive_record.update',
		'sensitive_record.status_change',
	);

	public static function table() {
		return g2a_crm_table( 'webhooks' );
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
	 * Find every active webhook subscribed to an event, plus any wildcard ("*")
	 * subscribers. Used by the dispatcher.
	 *
	 * @param string $event
	 * @return array
	 */
	public static function subscribers_for( $event ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE is_active = 1 AND (event = %s OR event = %s)",
				$event,
				'*'
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['event'] ) ) {
			$where[]    = 'event = %s';
			$prepared[] = sanitize_text_field( $args['event'] );
		}
		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$where[]    = 'is_active = %d';
			$prepared[] = $args['is_active'] ? 1 : 0;
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
		if ( empty( $row['name'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Webhook name is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['target_url'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'target_url is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['event'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'event is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['secret'] ) ) {
			$row['secret'] = self::generate_secret();
		}

		global $wpdb;
		$now              = g2a_crm_now();
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );
		G2A_CRM_Audit_Log::record( 'webhook.create', 'webhook', $id, null, self::for_audit( $new ) );
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Webhook not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$patch = self::sanitize( $data );
		if ( empty( $patch ) ) {
			return $existing;
		}
		$patch['updated_at'] = g2a_crm_now();

		global $wpdb;
		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff( self::for_audit( $existing ), self::for_audit( $new ) );
		if ( ! empty( $diff['new'] ) ) {
			G2A_CRM_Audit_Log::record( 'webhook.update', 'webhook', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Webhook not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'webhook.delete', 'webhook', (int) $id, self::for_audit( $existing ), null );
		return true;
	}

	public static function record_attempt( $id, $status, $last_sent_at ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'last_status'  => sanitize_key( $status ),
				'last_sent_at' => $last_sent_at,
				'updated_at'   => g2a_crm_now(),
			),
			array( 'id' => (int) $id )
		);
	}

	public static function generate_secret() {
		// 32 bytes hex = 64 chars. Predictable size for signing.
		if ( function_exists( 'wp_generate_password' ) ) {
			return bin2hex( random_bytes( 24 ) );
		}
		return bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Strip secret from audit payloads.
	 */
	private static function for_audit( array $row ) {
		$out = $row;
		if ( isset( $out['secret'] ) ) {
			$out['secret'] = $out['secret'] ? '[REDACTED]' : '';
		}
		return $out;
	}

	private static function sanitize( array $data ) {
		$row = array();
		if ( array_key_exists( 'name', $data ) ) {
			$row['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'event', $data ) ) {
			$event = sanitize_text_field( (string) $data['event'] );
			$row['event'] = ( '*' === $event || in_array( $event, self::EVENTS, true ) ) ? $event : '';
			if ( '' === $row['event'] && '' !== $event ) {
				// Allow custom event names too (e.g. plugin extensions) — accept dotted slugs.
				if ( preg_match( '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $event ) ) {
					$row['event'] = $event;
				}
			}
			if ( '' === $row['event'] ) {
				unset( $row['event'] );
			}
		}
		if ( array_key_exists( 'target_url', $data ) ) {
			$url = esc_url_raw( (string) $data['target_url'], array( 'http', 'https' ) );
			$row['target_url'] = $url;
		}
		if ( array_key_exists( 'secret', $data ) ) {
			// Reject the masked placeholder — preserves existing secret.
			$secret = (string) $data['secret'];
			if ( '' !== $secret && ! preg_match( '/^\*+$/', $secret ) ) {
				$row['secret'] = sanitize_text_field( $secret );
			}
		}
		if ( array_key_exists( 'is_active', $data ) ) {
			$row['is_active'] = $data['is_active'] ? 1 : 0;
		}
		return $row;
	}
}
