<?php
/**
 * Shared helper functions.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'g2a_crm_table' ) ) {
	/**
	 * Return the full prefixed table name for a CRM table.
	 *
	 * @param string $name Bare table name (e.g. "customers").
	 * @return string
	 */
	function g2a_crm_table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'g2a_crm_' . $name;
	}
}

if ( ! function_exists( 'g2a_crm_sanitize_phone' ) ) {
	/**
	 * Normalize a phone number: keep digits and a leading "+".
	 *
	 * @param string $phone Raw phone string.
	 * @return string
	 */
	function g2a_crm_sanitize_phone( $phone ) {
		$phone = (string) $phone;
		if ( '' === $phone ) {
			return '';
		}
		$has_plus = isset( $phone[0] ) && '+' === $phone[0];
		$digits   = preg_replace( '/[^0-9]/', '', $phone );
		return $has_plus && '' !== $digits ? '+' . $digits : $digits;
	}
}

if ( ! function_exists( 'g2a_crm_now' ) ) {
	/**
	 * UTC MySQL timestamp.
	 */
	function g2a_crm_now() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'g2a_crm_parse_pagination' ) ) {
	/**
	 * Parse pagination args from a REST request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{per_page:int,page:int,offset:int}
	 */
	function g2a_crm_parse_pagination( $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		if ( $per_page < 1 ) {
			$per_page = 25;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}
		if ( $page < 1 ) {
			$page = 1;
		}

		return array(
			'per_page' => $per_page,
			'page'     => $page,
			'offset'   => ( $page - 1 ) * $per_page,
		);
	}
}

if ( ! function_exists( 'g2a_crm_request_ip' ) ) {
	/**
	 * Best-effort client IP for audit logs.
	 */
	function g2a_crm_request_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			if ( false !== strpos( $value, ',' ) ) {
				$value = trim( explode( ',', $value )[0] );
			}
			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				return $value;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'g2a_crm_user_agent' ) ) {
	/**
	 * Sanitized user agent string, truncated.
	 */
	function g2a_crm_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		return substr( $ua, 0, 500 );
	}
}

if ( ! function_exists( 'g2a_crm_allowed_status' ) ) {
	/**
	 * Validate that a candidate value is in an allow-list, else return the default.
	 *
	 * @param mixed  $value    Candidate.
	 * @param array  $allowed  Allowed values.
	 * @param string $default  Default fallback.
	 * @return string
	 */
	function g2a_crm_allowed_status( $value, $allowed, $default ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
