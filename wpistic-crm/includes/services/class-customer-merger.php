<?php
/**
 * Customer merge service.
 *
 * Moves every related row from a source customer to a target customer, then
 * deletes the source. Designed to be idempotent-friendly and audit-heavy:
 * every per-table count is recorded so the merge can be reconstructed later.
 *
 * Tables touched (source → target):
 *   - leads.customer_id
 *   - bookings.customer_id
 *   - waivers.customer_id
 *   - memberships.customer_id
 *   - messages.customer_id
 *   - class_students.customer_id  (UNIQUE class_id+customer_id — drop source dupes first)
 *   - customer_tags.customer_id   (UNIQUE customer_id+tag_id — drop source dupes first)
 *   - customer_meta.customer_id
 *   - notes (related_type=customer, related_id)
 *   - sensitive_records.customer_id
 *
 * Conflict resolution for contact fields: prefer target's non-empty value;
 * fall back to source if target is blank. The CRM operator picks which row
 * is "target" — that's the row that survives — so this favors their intent.
 *
 * The merge runs inside an explicit transaction when the storage engine
 * supports it. If anything mid-merge fails, we roll back and return the
 * error.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Customer_Merger {

	/**
	 * Execute a merge.
	 *
	 * @param int $source_id Customer to be merged AWAY (will be deleted).
	 * @param int $target_id Customer to be merged INTO (survives).
	 * @return array|WP_Error Summary on success.
	 */
	public static function merge( $source_id, $target_id ) {
		$source_id = (int) $source_id;
		$target_id = (int) $target_id;

		if ( $source_id === $target_id ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Source and target must be different customers.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( $source_id < 1 || $target_id < 1 ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Both source and target customer ids are required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$source = G2A_CRM_Customers_Repository::find( $source_id );
		$target = G2A_CRM_Customers_Repository::find( $target_id );

		if ( ! $source ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Source customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		if ( ! $target ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Target customer not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		$counts = array();
		$wpdb->query( 'START TRANSACTION' );

		try {
			$counts['leads']             = self::reassign_simple( $wpdb, 'leads', $source_id, $target_id );
			$counts['bookings']          = self::reassign_simple( $wpdb, 'bookings', $source_id, $target_id );
			$counts['waivers']           = self::reassign_simple( $wpdb, 'waivers', $source_id, $target_id );
			$counts['memberships']       = self::reassign_simple( $wpdb, 'memberships', $source_id, $target_id );
			$counts['messages']          = self::reassign_simple( $wpdb, 'messages', $source_id, $target_id );
			$counts['customer_meta']     = self::reassign_simple( $wpdb, 'customer_meta', $source_id, $target_id );
			$counts['sensitive_records'] = self::reassign_simple( $wpdb, 'sensitive_records', $source_id, $target_id );

			// Class students: UNIQUE on (class_id, customer_id). Drop source rows
			// whose class is already on target before reassigning the rest.
			$counts['class_students_dropped'] = self::dedupe_pivot(
				$wpdb,
				g2a_crm_table( 'class_students' ),
				'class_id',
				$source_id,
				$target_id
			);
			$counts['class_students'] = self::reassign_simple( $wpdb, 'class_students', $source_id, $target_id );

			// Customer tags: UNIQUE on (customer_id, tag_id). Same pattern.
			$counts['customer_tags_dropped'] = self::dedupe_pivot(
				$wpdb,
				g2a_crm_table( 'customer_tags' ),
				'tag_id',
				$source_id,
				$target_id
			);
			$counts['customer_tags'] = self::reassign_simple( $wpdb, 'customer_tags', $source_id, $target_id );

			// Notes are polymorphic — only touch rows with related_type=customer.
			$notes_table   = g2a_crm_table( 'notes' );
			$counts['notes'] = (int) $wpdb->query(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					"UPDATE {$notes_table} SET related_id = %d WHERE related_type = 'customer' AND related_id = %d",
					$target_id,
					$source_id
				)
			);

			// Integrations rows are polymorphic too (local_type discriminator).
			$integrations_table = g2a_crm_table( 'integrations' );
			$counts['integrations'] = (int) $wpdb->query(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					"UPDATE {$integrations_table} SET local_id = %d WHERE local_type = 'customer' AND local_id = %d",
					$target_id,
					$source_id
				)
			);

			// Update target with backfilled contact fields where the target is blank.
			$backfill = self::compute_backfill( $source, $target );
			if ( ! empty( $backfill ) ) {
				$backfill['updated_at'] = g2a_crm_now();
				$wpdb->update( G2A_CRM_Customers_Repository::table(), $backfill, array( 'id' => $target_id ) );
				$counts['target_backfilled_fields'] = array_keys( $backfill );
			} else {
				$counts['target_backfilled_fields'] = array();
			}

			// Finally, delete the source customer row.
			$wpdb->delete( G2A_CRM_Customers_Repository::table(), array( 'id' => $source_id ), array( '%d' ) );

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'g2a_crm_merge_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		// Audit AFTER commit so the trail is durable. Record both directions so
		// the target's timeline shows the absorbed history and the source's
		// final state remains in the log.
		G2A_CRM_Audit_Log::record(
			'customer.merge_source',
			'customer',
			$source_id,
			$source,
			null,
			sprintf( 'Merged into customer #%d', $target_id )
		);
		G2A_CRM_Audit_Log::record(
			'customer.merge_target',
			'customer',
			$target_id,
			array( 'before' => self::snapshot_for_audit( $target ) ),
			array(
				'absorbed_customer_id' => $source_id,
				'counts'               => $counts,
				'backfilled'           => $counts['target_backfilled_fields'],
			)
		);

		return array(
			'target_id' => $target_id,
			'source_id' => $source_id,
			'counts'    => $counts,
		);
	}

	/**
	 * UPDATE the customer_id column on a related table.
	 *
	 * @return int Affected row count.
	 */
	private static function reassign_simple( $wpdb, $table_suffix, $source_id, $target_id ) {
		$table = g2a_crm_table( $table_suffix );
		$count = $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$table} SET customer_id = %d WHERE customer_id = %d",
				$target_id,
				$source_id
			)
		);
		if ( false === $count ) {
			throw new \RuntimeException( sprintf( 'Failed to reassign %s rows: %s', $table_suffix, $wpdb->last_error ) );
		}
		return (int) $count;
	}

	/**
	 * Pivot-table dedupe: delete source rows whose pivot key already exists on
	 * the target. Must run BEFORE reassign_simple to avoid UNIQUE collisions.
	 *
	 * @param string $pivot_table Fully prefixed table name.
	 * @param string $pivot_key   Column that pairs with customer_id in the unique key.
	 * @return int Rows deleted.
	 */
	private static function dedupe_pivot( $wpdb, $pivot_table, $pivot_key, $source_id, $target_id ) {
		$target_keys = $wpdb->get_col(
			$wpdb->prepare( "SELECT {$pivot_key} FROM {$pivot_table} WHERE customer_id = %d", $target_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( empty( $target_keys ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $target_keys ), '%d' ) );
		$args         = array_merge( array( $source_id ), array_map( 'intval', $target_keys ) );
		$count        = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$pivot_table} WHERE customer_id = %d AND {$pivot_key} IN ({$placeholders})", $args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( false === $count ) {
			throw new \RuntimeException( sprintf( 'Failed to dedupe pivot table %s: %s', $pivot_table, $wpdb->last_error ) );
		}
		return (int) $count;
	}

	/**
	 * Compute fields to backfill on the target from the source, only filling
	 * blanks on the target. Target wins on every conflict.
	 *
	 * @param array $source
	 * @param array $target
	 * @return array Patch (may be empty).
	 */
	private static function compute_backfill( array $source, array $target ) {
		$fields = array(
			'email',
			'phone',
			'address_line_1',
			'address_line_2',
			'city',
			'state',
			'zip',
			'date_of_birth',
		);
		$patch = array();
		foreach ( $fields as $field ) {
			$target_value = isset( $target[ $field ] ) ? trim( (string) $target[ $field ] ) : '';
			$source_value = isset( $source[ $field ] ) ? trim( (string) $source[ $field ] ) : '';
			if ( '' === $target_value && '' !== $source_value ) {
				$patch[ $field ] = $source[ $field ];
			}
		}
		// Combine source notes_summary into target if target's is blank.
		if ( empty( trim( (string) ( $target['notes_summary'] ?? '' ) ) ) && ! empty( $source['notes_summary'] ) ) {
			$patch['notes_summary'] = $source['notes_summary'];
		}
		return $patch;
	}

	private static function snapshot_for_audit( array $row ) {
		// Trim the payload — we only need identifying / change-tracking fields.
		$keep = array( 'id', 'first_name', 'last_name', 'email', 'phone', 'customer_type', 'status', 'membership_status', 'waiver_status', 'created_at' );
		$out  = array();
		foreach ( $keep as $k ) {
			if ( array_key_exists( $k, $row ) ) {
				$out[ $k ] = $row[ $k ];
			}
		}
		return $out;
	}
}
