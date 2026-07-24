<?php
/**
 * Suppression list — addresses we MUST NOT message.
 *
 * Sources:
 *   - manual       : staff added it via the UI
 *   - hard_bounce  : provider reported a permanent delivery failure
 *   - complaint    : recipient marked the message as spam / replied STOP
 *   - unsubscribe  : recipient hit the unsubscribe footer link
 *   - send_failure : wp_mail or Twilio returned a permanent failure on send
 *
 * Normalization rules:
 *   - email  → strtolower(trim(...))
 *   - sms    → digits only, leading "+" preserved
 *
 * The dispatcher MUST consult is_suppressed() before every send. Bypassing
 * the suppression list could trigger ISP/carrier reputation penalties that
 * cost an FFL their entire mailing channel.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Suppressions_Repository {

	const CHANNELS = array( 'email', 'sms' );

	const SOURCES = array( 'manual', 'hard_bounce', 'complaint', 'unsubscribe', 'send_failure' );

	public static function table() {
		return g2a_crm_table( 'suppressions' );
	}

	public static function normalize( $channel, $address ) {
		$address = (string) $address;
		if ( 'email' === $channel ) {
			return strtolower( trim( $address ) );
		}
		if ( 'sms' === $channel ) {
			$digits = preg_replace( '/[^0-9+]/', '', $address );
			// Collapse multiple "+" to single leading "+".
			if ( strpos( $digits, '+' ) !== false ) {
				$digits = '+' . preg_replace( '/[^0-9]/', '', $digits );
			}
			return $digits;
		}
		return $address;
	}

	public static function is_suppressed( $channel, $address ) {
		$channel = sanitize_key( (string) $channel );
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			return false;
		}
		$normalized = self::normalize( $channel, $address );
		if ( '' === $normalized ) {
			return false;
		}
		global $wpdb;
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE channel = %s AND address = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$channel,
				$normalized
			)
		);
		return $found > 0;
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
	 * Add to the suppression list. Idempotent — adding the same (channel,
	 * address) again just refreshes reason/source. Returns the row.
	 *
	 * @return array|WP_Error
	 */
	public static function add( $channel, $address, $reason = '', $source = 'manual' ) {
		$channel = sanitize_key( (string) $channel );
		if ( ! in_array( $channel, self::CHANNELS, true ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'channel must be email or sms.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$normalized = self::normalize( $channel, $address );
		if ( '' === $normalized ) {
			return new WP_Error( 'g2a_crm_validation', __( 'address is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		$source = in_array( $source, self::SOURCES, true ) ? $source : 'manual';
		$reason = sanitize_text_field( (string) $reason );

		global $wpdb;
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE channel = %s AND address = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$channel,
				$normalized
			),
			ARRAY_A
		);
		if ( $existing ) {
			// Don't downgrade a hard-bounce row back to manual.
			$keep_source = self::source_rank( $existing['source'] ) > self::source_rank( $source ) ? $existing['source'] : $source;
			$wpdb->update(
				self::table(),
				array(
					'reason' => $reason ?: $existing['reason'],
					'source' => $keep_source,
				),
				array( 'id' => (int) $existing['id'] )
			);
			return self::find( (int) $existing['id'] );
		}

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'channel'    => $channel,
				'address'    => $normalized,
				'reason'     => $reason,
				'source'     => $source,
				'created_at' => g2a_crm_now(),
			)
		);
		if ( false === $inserted ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		$row = self::find( (int) $wpdb->insert_id );
		G2A_CRM_Audit_Log::record( 'suppression.add', 'suppression', (int) $row['id'], null, array( 'channel' => $channel, 'source' => $source ) );
		return $row;
	}

	public static function remove( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Suppression not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'suppression.remove', 'suppression', (int) $id, array( 'channel' => $existing['channel'], 'source' => $existing['source'] ), null );
		return true;
	}

	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$per_page = isset( $args['per_page'] ) ? min( 200, max( 1, (int) $args['per_page'] ) ) : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['channel'] ) ) {
			$where[]    = 'channel = %s';
			$prepared[] = sanitize_key( $args['channel'] );
		}
		if ( ! empty( $args['source'] ) ) {
			$where[]    = 'source = %s';
			$prepared[] = sanitize_key( $args['source'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like       = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]    = 'address LIKE %s';
			$prepared[] = $like;
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

	/**
	 * Higher rank = stickier. A hard-bounce shouldn't be overwritten by a
	 * later manual entry; a manual entry should yield to a later bounce.
	 */
	private static function source_rank( $source ) {
		$ranks = array(
			'manual'       => 1,
			'unsubscribe'  => 2,
			'send_failure' => 3,
			'complaint'    => 4,
			'hard_bounce'  => 5,
		);
		return $ranks[ $source ] ?? 0;
	}
}
