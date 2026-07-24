<?php
/**
 * Audit log service.
 *
 * Every sensitive write should call G2A_CRM_Audit_Log::record(). Failures here
 * MUST NOT break the operation that triggered them — log and move on.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Audit_Log {

	/**
	 * Record an audit entry.
	 *
	 * @param string     $action      e.g. "customer.create", "lead.status_change".
	 * @param string     $object_type e.g. "customer", "lead", "task".
	 * @param int        $object_id   Affected record id (0 if N/A).
	 * @param array|null $old_value   Pre-change snapshot (will be JSON-encoded).
	 * @param array|null $new_value   Post-change snapshot.
	 * @param string     $reason      Optional staff note.
	 */
	public static function record( $action, $object_type, $object_id = 0, $old_value = null, $new_value = null, $reason = '' ) {
		global $wpdb;

		$row = array(
			'actor_id'    => get_current_user_id() ?: null,
			'action'      => sanitize_key( $action ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => max( 0, (int) $object_id ),
			'old_value'   => null === $old_value ? null : wp_json_encode( $old_value ),
			'new_value'   => null === $new_value ? null : wp_json_encode( $new_value ),
			'ip_address'  => g2a_crm_request_ip(),
			'user_agent'  => g2a_crm_user_agent(),
			'reason'      => $reason ? wp_kses_post( $reason ) : null,
			'created_at'  => g2a_crm_now(),
		);

		$result = $wpdb->insert(
			g2a_crm_table( 'audit_logs' ),
			$row,
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'G2A CRM audit log insert failed: ' . $wpdb->last_error );
		}

		/**
		 * Fired after a CRM state change is recorded. Subscribers (e.g. the
		 * outbound webhook dispatcher) can react to specific events.
		 *
		 * @param string     $action      Dotted action name (e.g. "customer.create").
		 * @param string     $object_type Object type (e.g. "customer").
		 * @param int        $object_id   Object id.
		 * @param array|null $old_value   Pre-change payload (already diffed/redacted by caller).
		 * @param array|null $new_value   Post-change payload.
		 * @param int        $actor_id    User id that performed the change.
		 */
		do_action(
			'g2a_crm_event',
			$row['action'],
			$row['object_type'],
			$row['object_id'],
			$old_value,
			$new_value,
			(int) $row['actor_id']
		);
	}

	/**
	 * Diff two associative arrays and return only the changed keys.
	 *
	 * Useful when you want a small, targeted audit payload instead of dumping
	 * the whole record.
	 *
	 * @param array $before
	 * @param array $after
	 * @return array{old:array, new:array}
	 */
	public static function diff( array $before, array $after ) {
		$old = array();
		$new = array();
		foreach ( $after as $key => $value ) {
			$prev = $before[ $key ] ?? null;
			if ( $prev !== $value ) {
				$old[ $key ] = $prev;
				$new[ $key ] = $value;
			}
		}
		return array(
			'old' => $old,
			'new' => $new,
		);
	}

	/**
	 * Recent log entries, paginated.
	 *
	 * @param array $args
	 * @return array{rows:array,total:int}
	 */
	public static function list( array $args = array() ) {
		global $wpdb;
		$table = g2a_crm_table( 'audit_logs' );

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['object_type'] ) ) {
			$where[]    = 'object_type = %s';
			$prepared[] = sanitize_key( $args['object_type'] );
		}
		if ( ! empty( $args['object_id'] ) ) {
			$where[]    = 'object_id = %d';
			$prepared[] = (int) $args['object_id'];
		}
		if ( ! empty( $args['actor_id'] ) ) {
			$where[]    = 'actor_id = %d';
			$prepared[] = (int) $args['actor_id'];
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
}
