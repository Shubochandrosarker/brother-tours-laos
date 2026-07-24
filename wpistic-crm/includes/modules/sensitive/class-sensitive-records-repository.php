<?php
/**
 * Sensitive (compliance-aware) records data access.
 *
 * This module is the compliance-sensitive workflow layer described in blueprint §5.9:
 * FFL transfer notes, NICS reference status, A&D references, staff checklists,
 * and the audit trail around them.
 *
 * SAFETY POSTURE:
 * - This module does NOT make legal decisions.
 * - It does NOT auto-approve regulated actions.
 * - It is a workflow + checklist + audit-trail layer for human staff.
 * - Access is gated by capability AND by an optional role allowlist
 *   (settings.restrict_sensitive_view_to_roles) so an org can layer in extra
 *   restriction beyond the WP cap.
 *
 * Every read of these records is audit-logged. Compliance auditors want to
 * answer "who looked at this and when" — not just "who changed it".
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Sensitive_Records_Repository {

	const RECORD_TYPES = array(
		'ffl_transfer',
		'nics_reference',
		'ad_reference',
		'background_check',
		'compliance_review',
		'staff_review',
		'legal_hold',
		'other',
	);

	const STATUSES = array(
		'inquiry_received',
		'staff_review_required',
		'documents_pending',
		'verification_in_progress',
		'waiting_external',
		'ready_for_staff_action',
		'completed',
		'cancelled',
		'rejected',
		'archived',
	);

	/** Open / actionable statuses — anything not in here is considered terminal. */
	const OPEN_STATUSES = array(
		'inquiry_received',
		'staff_review_required',
		'documents_pending',
		'verification_in_progress',
		'waiting_external',
		'ready_for_staff_action',
	);

	public static function table() {
		return g2a_crm_table( 'sensitive_records' );
	}

	/**
	 * Does the current user pass the optional role-allowlist gate?
	 *
	 * The WP capability is checked separately (in REST controllers). This
	 * function adds an extra layer: even if you have the capability via a
	 * custom role, you must also be in the configured allowlist.
	 *
	 * @return bool
	 */
	public static function role_allowed_for_current_user() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$allowed = G2A_CRM_Settings::get( 'restrict_sensitive_view_to_roles', array() );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			return true; // No allowlist configured = no extra gate.
		}
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}
		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $allowed, true ) ) {
				return true;
			}
		}
		return false;
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

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 25;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]    = 'customer_id = %d';
			$prepared[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['record_type'] ) ) {
			$where[]    = 'record_type = %s';
			$prepared[] = sanitize_key( $args['record_type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['open_only'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( self::OPEN_STATUSES ), '%s' ) );
			$where[]      = "status IN ({$placeholders})";
			foreach ( self::OPEN_STATUSES as $s ) {
				$prepared[] = $s;
			}
		}
		if ( ! empty( $args['handled_by'] ) ) {
			$where[]    = 'handled_by = %d';
			$prepared[] = (int) $args['handled_by'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like        = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]     = '(reference_code LIKE %s OR body LIKE %s)';
			$prepared[]  = $like;
			$prepared[]  = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( $count_sql, $prepared ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d";
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
		if ( empty( $row['customer_id'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'customer_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( empty( $row['record_type'] ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'record_type is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$now              = g2a_crm_now();
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		if ( empty( $row['status'] ) ) {
			$row['status'] = 'inquiry_received';
		}

		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		$id  = (int) $wpdb->insert_id;
		$new = self::find( $id );

		// Audit log carries the customer-id link so it appears on the customer's timeline.
		G2A_CRM_Audit_Log::record(
			'sensitive_record.create',
			'sensitive_record',
			$id,
			null,
			self::for_audit( $new ),
			sprintf( 'customer #%d, type=%s', (int) $new['customer_id'], $new['record_type'] )
		);
		return $new;
	}

	public static function update( $id, array $data ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Sensitive record not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		$patch = self::sanitize( $data );
		// customer_id is immutable once created — moving sensitive data between
		// customers should require a delete + re-create with full audit trail.
		unset( $patch['customer_id'] );
		if ( empty( $patch ) ) {
			return $existing;
		}

		// Closing transitions get a timestamp.
		if ( isset( $patch['status'] ) && ! in_array( $patch['status'], self::OPEN_STATUSES, true ) && in_array( $existing['status'], self::OPEN_STATUSES, true ) ) {
			$patch['closed_at'] = g2a_crm_now();
		}
		// Re-opening clears closed_at.
		if ( isset( $patch['status'] ) && in_array( $patch['status'], self::OPEN_STATUSES, true ) && ! in_array( $existing['status'], self::OPEN_STATUSES, true ) ) {
			$patch['closed_at']     = null;
			$patch['closed_reason'] = null;
		}

		$patch['updated_at'] = g2a_crm_now();

		global $wpdb;
		$wpdb->update( self::table(), $patch, array( 'id' => (int) $id ) );

		$new  = self::find( $id );
		$diff = G2A_CRM_Audit_Log::diff(
			self::for_audit( $existing ),
			self::for_audit( $new )
		);
		if ( ! empty( $diff['new'] ) ) {
			$action = isset( $diff['new']['status'] ) ? 'sensitive_record.status_change' : 'sensitive_record.update';
			G2A_CRM_Audit_Log::record( $action, 'sensitive_record', (int) $id, $diff['old'], $diff['new'] );
		}
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Sensitive record not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'sensitive_record.delete', 'sensitive_record', (int) $id, self::for_audit( $existing ), null );
		return true;
	}

	/**
	 * Log a read access. Call from controllers on list/get so every view of a
	 * regulated record is traceable to a user + IP + timestamp.
	 *
	 * @param string $what  Either 'view' (single) or 'list' (search).
	 * @param int    $id    Record id, 0 for list operations.
	 * @param array  $meta  Optional metadata (search args, customer filter).
	 */
	public static function log_read( $what, $id = 0, array $meta = array() ) {
		$action = 'sensitive_record.read_' . ( 'list' === $what ? 'list' : 'view' );
		G2A_CRM_Audit_Log::record( $action, 'sensitive_record', (int) $id, null, $meta ?: null );
	}

	/**
	 * Return only the fields safe to dump into an audit payload. The full body
	 * may contain regulated text — we redact it to a length marker and a hash
	 * so we can prove it changed without re-storing it in the log.
	 *
	 * @param array $row
	 * @return array
	 */
	private static function for_audit( array $row ) {
		$out = array(
			'customer_id'    => (int) ( $row['customer_id'] ?? 0 ),
			'record_type'    => $row['record_type'] ?? '',
			'status'         => $row['status'] ?? '',
			'reference_code' => $row['reference_code'] ?? '',
			'handled_by'     => isset( $row['handled_by'] ) ? (int) $row['handled_by'] : null,
			'document_ref'   => $row['document_ref'] ?? '',
		);
		if ( ! empty( $row['body'] ) ) {
			$out['body_marker'] = '[redacted:' . strlen( (string) $row['body'] ) . ':' . substr( hash( 'sha256', (string) $row['body'] ), 0, 12 ) . ']';
		}
		if ( ! empty( $row['checklist'] ) ) {
			$out['checklist_marker'] = '[redacted:' . strlen( (string) $row['checklist'] ) . ']';
		}
		if ( ! empty( $row['closed_at'] ) ) {
			$out['closed_at'] = $row['closed_at'];
		}
		return $out;
	}

	private static function sanitize( array $data ) {
		$row = array();

		if ( array_key_exists( 'customer_id', $data ) ) {
			$row['customer_id'] = (int) $data['customer_id'];
		}
		if ( array_key_exists( 'record_type', $data ) ) {
			$value = sanitize_key( (string) $data['record_type'] );
			$row['record_type'] = in_array( $value, self::RECORD_TYPES, true ) ? $value : 'other';
		}
		if ( array_key_exists( 'status', $data ) ) {
			$value = sanitize_key( (string) $data['status'] );
			$row['status'] = in_array( $value, self::STATUSES, true ) ? $value : '';
			if ( '' === $row['status'] ) {
				unset( $row['status'] );
			}
		}
		if ( array_key_exists( 'reference_code', $data ) ) {
			$row['reference_code'] = sanitize_text_field( (string) $data['reference_code'] );
		}
		if ( array_key_exists( 'document_ref', $data ) ) {
			$row['document_ref'] = $data['document_ref'] ? esc_url_raw( (string) $data['document_ref'] ) : null;
		}
		if ( array_key_exists( 'body', $data ) ) {
			$row['body'] = $data['body'] ? wp_kses_post( (string) $data['body'] ) : null;
		}
		if ( array_key_exists( 'checklist', $data ) ) {
			$row['checklist'] = self::sanitize_checklist( $data['checklist'] );
		}
		if ( array_key_exists( 'handled_by', $data ) ) {
			$row['handled_by'] = $data['handled_by'] ? (int) $data['handled_by'] : null;
		}
		if ( array_key_exists( 'closed_reason', $data ) ) {
			$row['closed_reason'] = $data['closed_reason'] ? sanitize_textarea_field( (string) $data['closed_reason'] ) : null;
		}

		return $row;
	}

	private static function sanitize_checklist( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$clean = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$clean[] = array(
				'label'       => $label,
				'done'        => ! empty( $item['done'] ),
				'completed_by' => isset( $item['completed_by'] ) ? (int) $item['completed_by'] : null,
				'completed_at' => isset( $item['completed_at'] ) ? sanitize_text_field( (string) $item['completed_at'] ) : null,
			);
		}
		return $clean ? wp_json_encode( $clean ) : null;
	}
}
