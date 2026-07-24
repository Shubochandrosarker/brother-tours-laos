<?php
/**
 * Saved views — per-user, per-resource filter presets.
 *
 * Stored as a JSON-encoded array in user_meta under G2A_CRM_SAVED_VIEWS_META.
 * Each view is { id, resource, name, filters: {...}, created_at }.
 *
 * Per-user scoping is enforced at the storage layer — callers cannot read or
 * mutate another user's views. The id space is per-user (a monotonically
 * increasing counter in the same meta payload), so there's no cross-user id
 * collision attack surface.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Saved_Views {

	const META_KEY        = 'g2a_crm_saved_views';
	const MAX_PER_USER    = 50;
	const SUPPORTED_RESOURCES = array( 'customers', 'leads', 'bookings', 'memberships', 'tasks', 'classes', 'waivers', 'messages' );

	/**
	 * Return all views owned by the current user, optionally filtered to a resource.
	 *
	 * @param string|null $resource
	 * @return array
	 */
	public static function list_for_current_user( $resource = null ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return array();
		}
		$views = self::load( $uid );
		if ( $resource ) {
			$resource = sanitize_key( $resource );
			$views    = array_values( array_filter( $views, static function ( $v ) use ( $resource ) {
				return isset( $v['resource'] ) && $v['resource'] === $resource;
			} ) );
		}
		return $views;
	}

	/**
	 * Persist a new view for the current user.
	 *
	 * @return array|WP_Error
	 */
	public static function create( array $data ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'Login required.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}

		$resource = sanitize_key( (string) ( $data['resource'] ?? '' ) );
		$name     = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$filters  = isset( $data['filters'] ) && is_array( $data['filters'] ) ? $data['filters'] : array();

		if ( ! in_array( $resource, self::SUPPORTED_RESOURCES, true ) ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Unsupported resource.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}
		if ( '' === $name ) {
			return new WP_Error( 'g2a_crm_validation', __( 'View name is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$views = self::load( $uid );
		if ( count( $views ) >= self::MAX_PER_USER ) {
			return new WP_Error( 'g2a_crm_conflict', __( 'Maximum saved views reached. Delete an unused view first.', 'guns2ammo-crm' ), array( 'status' => 409 ) );
		}

		$next_id = self::next_id( $views );
		$view    = array(
			'id'         => $next_id,
			'resource'   => $resource,
			'name'       => $name,
			'filters'    => self::sanitize_filters( $filters ),
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		$views[] = $view;
		self::save( $uid, $views );
		return $view;
	}

	public static function delete( $view_id ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return new WP_Error( 'g2a_crm_unauthenticated', __( 'Login required.', 'guns2ammo-crm' ), array( 'status' => 401 ) );
		}
		$views     = self::load( $uid );
		$remaining = array_values( array_filter( $views, static function ( $v ) use ( $view_id ) {
			return (int) $v['id'] !== (int) $view_id;
		} ) );
		if ( count( $remaining ) === count( $views ) ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'View not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		self::save( $uid, $remaining );
		return true;
	}

	private static function load( $uid ) {
		$raw = get_user_meta( (int) $uid, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		// Drop anything malformed defensively.
		return array_values( array_filter( $raw, static function ( $v ) {
			return is_array( $v ) && isset( $v['id'], $v['resource'], $v['name'] );
		} ) );
	}

	private static function save( $uid, array $views ) {
		update_user_meta( (int) $uid, self::META_KEY, $views );
	}

	private static function next_id( array $views ) {
		$max = 0;
		foreach ( $views as $v ) {
			if ( isset( $v['id'] ) && (int) $v['id'] > $max ) {
				$max = (int) $v['id'];
			}
		}
		return $max + 1;
	}

	/**
	 * Whitelist filter keys + values. Filters are forwarded straight to the
	 * list REST endpoint as query params, so anything that's not a plain
	 * scalar gets dropped to keep stored views safe to round-trip.
	 */
	private static function sanitize_filters( array $filters ) {
		$out = array();
		foreach ( $filters as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			if ( is_bool( $v ) ) {
				$out[ $key ] = $v ? 1 : 0;
			} elseif ( is_scalar( $v ) ) {
				$out[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}
}
