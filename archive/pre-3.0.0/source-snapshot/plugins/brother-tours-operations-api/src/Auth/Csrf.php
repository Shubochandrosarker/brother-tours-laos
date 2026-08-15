<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Auth;

use WP_Error;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;

final class Csrf {

	/**
	 * Hash an opaque session token before it is stored server-side.
	 */
	public static function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function transient_key( string $hash ): string {
		return 'bt_ops_session_' . substr( $hash, 0, 48 );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function session_record(): ?array {
		$token = isset( $_COOKIE[ BTOA_SESSION_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ BTOA_SESSION_COOKIE ] ) ) : '';
		if ( '' === $token || strlen( $token ) < 32 ) {
			return null;
		}
		$record = get_transient( self::transient_key( self::hash_token( $token ) ) );
		if ( ! is_array( $record ) || empty( $record['user_id'] ) || empty( $record['expires'] ) || (int) $record['expires'] < time() ) {
			return null;
		}
		return $record;
	}

	/**
	 * Capability + CSRF permission helper for all API controllers.
	 *
	 * Application Password requests are not cookie-ambient and therefore do not
	 * require this plugin's CSRF token. The custom dashboard session does.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize( WP_REST_Request $request, string $capability = 'bt_manage_operations', bool $write = false ) {
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			return error( 'bt_ops_forbidden', __( 'You are not allowed to perform this operation.', 'brother-tours-operations-api' ), rest_authorization_required_code() );
		}

		if ( ! $write ) {
			return true;
		}

		$record = self::session_record();
		if ( null !== $record ) {
			$given    = (string) $request->get_header( 'X-BT-CSRF' );
			$expected = (string) ( $record['csrf'] ?? '' );
			if ( '' === $given || '' === $expected || ! hash_equals( $expected, $given ) ) {
				return error( 'bt_ops_csrf_failed', __( 'Invalid or expired CSRF token.', 'brother-tours-operations-api' ), 403 );
			}
			return true;
		}

		// WordPress cookie-auth fallback for administrators using the REST API
		// directly. Application Passwords carry an Authorization header and do not
		// need a nonce because they are not ambient browser credentials.
		$authorization = (string) $request->get_header( 'Authorization' );
		if ( '' !== $authorization ) {
			return true;
		}

		$wp_nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' !== $wp_nonce && wp_verify_nonce( $wp_nonce, 'wp_rest' ) ) {
			return true;
		}

		return error( 'bt_ops_csrf_required', __( 'CSRF protection is required for this request.', 'brother-tours-operations-api' ), 403 );
	}
}
