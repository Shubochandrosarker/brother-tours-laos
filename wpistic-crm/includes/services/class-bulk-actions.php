<?php
/**
 * Bulk action service.
 *
 * Runs a single action across a list of record IDs. Every action enforces the
 * corresponding per-row capability via the existing repository code paths —
 * the bulk endpoint is a fan-out, not a permission escalator. Failed rows
 * don't abort the batch; they're returned in the response with their error
 * so the caller can show a partial-success summary.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Bulk_Actions {

	const MAX_BATCH = 200;

	const CUSTOMER_ACTIONS = array( 'archive', 'activate', 'attach_tag', 'detach_tag', 'delete' );
	const LEAD_ACTIONS     = array( 'status', 'assign', 'delete' );

	/**
	 * Apply a bulk action to customers.
	 *
	 * @param string $action One of CUSTOMER_ACTIONS.
	 * @param int[]  $ids
	 * @param array  $params action-specific (tag_id, status, etc.)
	 * @return array{ok:int,failed:array<int,array>}
	 */
	public static function run_customers( $action, array $ids, array $params = array() ) {
		$ids = self::clean_ids( $ids );
		if ( ! in_array( $action, self::CUSTOMER_ACTIONS, true ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Unknown bulk action.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( ! self::can_for_action( 'customer', $action ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to perform this bulk action.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}

		$ok     = 0;
		$failed = array();
		foreach ( $ids as $id ) {
			$result = self::apply_customer_action( $action, $id, $params );
			if ( is_wp_error( $result ) ) {
				$failed[] = array( 'id' => $id, 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
			} else {
				$ok++;
			}
		}

		G2A_CRM_Audit_Log::record(
			'customer.bulk_' . $action,
			'customer',
			0,
			null,
			array( 'ids' => $ids, 'ok' => $ok, 'failed' => count( $failed ), 'params' => self::audit_safe_params( $params ) )
		);

		return array( 'ok' => $ok, 'failed' => $failed );
	}

	/**
	 * Apply a bulk action to leads.
	 */
	public static function run_leads( $action, array $ids, array $params = array() ) {
		$ids = self::clean_ids( $ids );
		if ( ! in_array( $action, self::LEAD_ACTIONS, true ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Unknown bulk action.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( ! self::can_for_action( 'lead', $action ) ) {
			return new WP_Error( 'g2a_crm_forbidden', __( 'You do not have permission to perform this bulk action.', 'guns2ammo-crm' ), array( 'status' => 403 ) );
		}

		$ok     = 0;
		$failed = array();
		foreach ( $ids as $id ) {
			$result = self::apply_lead_action( $action, $id, $params );
			if ( is_wp_error( $result ) ) {
				$failed[] = array( 'id' => $id, 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
			} else {
				$ok++;
			}
		}

		G2A_CRM_Audit_Log::record(
			'lead.bulk_' . $action,
			'lead',
			0,
			null,
			array( 'ids' => $ids, 'ok' => $ok, 'failed' => count( $failed ), 'params' => self::audit_safe_params( $params ) )
		);

		return array( 'ok' => $ok, 'failed' => $failed );
	}

	private static function apply_customer_action( $action, $id, array $params ) {
		switch ( $action ) {
			case 'archive':
				return G2A_CRM_Customers_Repository::update( $id, array( 'status' => 'archived' ) );
			case 'activate':
				return G2A_CRM_Customers_Repository::update( $id, array( 'status' => 'active' ) );
			case 'attach_tag':
				$tag_id = (int) ( $params['tag_id'] ?? 0 );
				if ( ! $tag_id ) {
					return new WP_Error( 'g2a_crm_validation', __( 'tag_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
				}
				return G2A_CRM_Tags_Repository::attach_to_customer( $id, $tag_id );
			case 'detach_tag':
				$tag_id = (int) ( $params['tag_id'] ?? 0 );
				if ( ! $tag_id ) {
					return new WP_Error( 'g2a_crm_validation', __( 'tag_id is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
				}
				return G2A_CRM_Tags_Repository::detach_from_customer( $id, $tag_id );
			case 'delete':
				return G2A_CRM_Customers_Repository::delete( $id );
		}
		return new WP_Error( 'g2a_crm_validation', 'Unknown action', array( 'status' => 400 ) );
	}

	private static function apply_lead_action( $action, $id, array $params ) {
		switch ( $action ) {
			case 'status':
				$status = sanitize_key( (string) ( $params['status'] ?? '' ) );
				if ( '' === $status ) {
					return new WP_Error( 'g2a_crm_validation', __( 'status is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
				}
				return G2A_CRM_Leads_Repository::update( $id, array( 'status' => $status ) );
			case 'assign':
				$user_id = isset( $params['assigned_to'] ) ? (int) $params['assigned_to'] : 0;
				return G2A_CRM_Leads_Repository::update( $id, array( 'assigned_to' => $user_id ?: null ) );
			case 'delete':
				return G2A_CRM_Leads_Repository::delete( $id );
		}
		return new WP_Error( 'g2a_crm_validation', 'Unknown action', array( 'status' => 400 ) );
	}

	/**
	 * Map (resource, action) → required capability.
	 */
	private static function can_for_action( $resource, $action ) {
		if ( 'customer' === $resource ) {
			if ( 'delete' === $action ) {
				return current_user_can( 'g2a_crm_delete_customers' );
			}
			if ( in_array( $action, array( 'attach_tag', 'detach_tag' ), true ) ) {
				return current_user_can( 'g2a_crm_edit_customers' ) && current_user_can( 'g2a_crm_manage_tags' );
			}
			return current_user_can( 'g2a_crm_edit_customers' );
		}
		if ( 'lead' === $resource ) {
			if ( 'delete' === $action ) {
				return current_user_can( 'g2a_crm_delete_leads' );
			}
			return current_user_can( 'g2a_crm_edit_leads' );
		}
		return false;
	}

	private static function clean_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( count( $ids ) > self::MAX_BATCH ) {
			$ids = array_slice( $ids, 0, self::MAX_BATCH );
		}
		return $ids;
	}

	private static function audit_safe_params( array $params ) {
		// Strip anything that looks like a token or secret. Keep enum-ish keys.
		$out = array();
		foreach ( $params as $k => $v ) {
			if ( is_scalar( $v ) && false === stripos( (string) $k, 'token' ) && false === stripos( (string) $k, 'secret' ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}
}
