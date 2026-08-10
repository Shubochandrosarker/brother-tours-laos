<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Auth;

use WP_Error;
use WP_REST_Request;
use WP_User;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

final class SessionController {

	private const LOGIN_WINDOW   = 15 * MINUTE_IN_SECONDS;
	private const MAX_ATTEMPTS   = 5;
	private const USER_META_KEY  = '_bt_ops_sessions';

	public function register(): void {
		add_filter( 'determine_current_user', array( $this, 'determine_current_user' ), 25 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'cors_headers' ), 20, 4 );
	}

	public function routes(): void {
		register_rest_route(
			BTOA_NAMESPACE,
			'/auth/session/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'login' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/auth/session',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'session' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/auth/session/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => Csrf::authorize( $request, 'edit_posts', true ),
			)
		);
		register_rest_route(
			BTOA_NAMESPACE,
			'/auth/session/revoke-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revoke_all' ),
				'permission_callback' => static fn( WP_REST_Request $request ) => Csrf::authorize( $request, 'edit_posts', true ),
			)
		);
	}

	/**
	 * Authenticate only REST requests for this plugin. The operations session
	 * never becomes a wp-admin login session.
	 */
	public function determine_current_user( $user_id ): int {
		if ( (int) $user_id > 0 ) {
			return (int) $user_id;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( ! str_contains( $uri, '/bt-ops/v1/' ) ) {
			return 0;
		}
		$record = Csrf::session_record();
		return null !== $record ? (int) $record['user_id'] : 0;
	}

	/**
	 * @return \WP_REST_Response|WP_Error
	 */
	public function login( WP_REST_Request $request ) {
		$rate = $this->rate_key();
		$hits = (int) get_transient( $rate );
		if ( $hits >= self::MAX_ATTEMPTS ) {
			return error( 'bt_ops_rate_limited', __( 'Too many sign-in attempts. Please try again later.', 'brother-tours-operations-api' ), 429 );
		}

		$params   = $request->get_json_params();
		$username = sanitize_text_field( (string) ( $params['username'] ?? '' ) );
		$password = (string) ( $params['password'] ?? '' );
		if ( '' === $username || '' === $password ) {
			return error( 'bt_ops_login_required', __( 'Username and password are required.', 'brother-tours-operations-api' ), 422 );
		}

		$user = wp_authenticate( $username, $password );
		if ( is_wp_error( $user ) || ! $user instanceof WP_User || ! user_can( $user, 'edit_posts' ) ) {
			set_transient( $rate, $hits + 1, self::LOGIN_WINDOW );
			return error( 'bt_ops_invalid_login', __( 'Invalid credentials or insufficient access.', 'brother-tours-operations-api' ), 401 );
		}

		delete_transient( $rate );
		$issued = $this->issue_session( $user );

		return response(
			array(
				'user'      => $this->format_user( $user ),
				'csrfToken' => $issued['csrf'],
				'expiresAt' => gmdate( 'c', (int) $issued['expires'] ),
			)
		);
	}

	/**
	 * @return \WP_REST_Response|WP_Error
	 */
	public function session() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return error( 'bt_ops_unauthorized', __( 'No active operations session.', 'brother-tours-operations-api' ), 401 );
		}

		$user   = wp_get_current_user();
		$record = Csrf::session_record();
		if ( null === $record ) {
			// A direct WP cookie/Application Password request can inspect the session,
			// but only dashboard sessions receive a dashboard CSRF token.
			return response(
				array(
					'user'      => $this->format_user( $user ),
					'csrfToken' => null,
					'expiresAt' => null,
				)
			);
		}

		return response(
			array(
				'user'      => $this->format_user( $user ),
				'csrfToken' => (string) $record['csrf'],
				'expiresAt' => gmdate( 'c', (int) $record['expires'] ),
			)
		);
	}

	public function logout() {
		$this->revoke_current_cookie();
		$this->clear_cookie();
		return response( array( 'loggedOut' => true ) );
	}

	public function revoke_all() {
		$user_id = get_current_user_id();
		$hashes  = get_user_meta( $user_id, self::USER_META_KEY, true );
		$hashes  = is_array( $hashes ) ? $hashes : array();
		foreach ( array_keys( $hashes ) as $hash ) {
			delete_transient( Csrf::transient_key( (string) $hash ) );
		}
		delete_user_meta( $user_id, self::USER_META_KEY );
		$this->clear_cookie();
		return response( array( 'revoked' => count( $hashes ) ) );
	}

	/**
	 * @return array{csrf:string,expires:int}
	 */
	private function issue_session( WP_User $user ): array {
		$token   = bin2hex( random_bytes( 32 ) );
		$hash    = Csrf::hash_token( $token );
		$csrf    = bin2hex( random_bytes( 24 ) );
		$expires = time() + (int) apply_filters( 'bt_ops_session_ttl', BTOA_SESSION_TTL );
		$record  = array(
			'user_id' => (int) $user->ID,
			'csrf'    => $csrf,
			'created' => time(),
			'expires' => $expires,
		);
		set_transient( Csrf::transient_key( $hash ), $record, max( 60, $expires - time() ) );

		$registry          = get_user_meta( (int) $user->ID, self::USER_META_KEY, true );
		$registry          = is_array( $registry ) ? $registry : array();
		$registry[ $hash ] = $expires;
		foreach ( $registry as $key => $expiry ) {
			if ( (int) $expiry < time() ) {
				unset( $registry[ $key ] );
			}
		}
		update_user_meta( (int) $user->ID, self::USER_META_KEY, $registry );

		$same_site = (string) apply_filters( 'bt_ops_cookie_samesite', is_ssl() ? 'None' : 'Lax' );
		setcookie(
			BTOA_SESSION_COOKIE,
			$token,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => in_array( $same_site, array( 'Lax', 'Strict', 'None' ), true ) ? $same_site : 'Lax',
			)
		);

		return array( 'csrf' => $csrf, 'expires' => $expires );
	}

	private function revoke_current_cookie(): void {
		$token = isset( $_COOKIE[ BTOA_SESSION_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ BTOA_SESSION_COOKIE ] ) ) : '';
		if ( '' === $token ) {
			return;
		}
		$hash = Csrf::hash_token( $token );
		delete_transient( Csrf::transient_key( $hash ) );

		$user_id  = get_current_user_id();
		$registry = get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( is_array( $registry ) && isset( $registry[ $hash ] ) ) {
			unset( $registry[ $hash ] );
			update_user_meta( $user_id, self::USER_META_KEY, $registry );
		}
	}

	private function clear_cookie(): void {
		setcookie(
			BTOA_SESSION_COOKIE,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => '/',
				'domain'   => '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function format_user( WP_User $user ): array {
		$capabilities = array_keys( array_filter( $user->allcaps ) );
		return array(
			'id'           => (int) $user->ID,
			'displayName'  => (string) $user->display_name,
			'email'        => (string) $user->user_email,
			'roles'        => array_values( $user->roles ),
			'capabilities' => $capabilities,
		);
	}

	private function rate_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'bt_ops_login_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Restrict credentialed CORS to explicit origins for bt-ops only.
	 *
	 * @param bool             $served Served.
	 * @param mixed            $result Result.
	 * @param WP_REST_Request  $request Request.
	 * @param mixed            $server Server.
	 */
	public function cors_headers( $served, $result, $request, $server ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $request instanceof WP_REST_Request || ! str_starts_with( $request->get_route(), '/' . BTOA_NAMESPACE ) ) {
			return (bool) $served;
		}
		$origin = get_http_origin();
		if ( $origin && in_array( $origin, $this->allowed_origins(), true ) ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ), true );
			header( 'Access-Control-Allow-Credentials: true', true );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-BT-CSRF, X-WP-Nonce', true );
			header( 'Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS', true );
			header( 'Vary: Origin', false );
		} elseif ( $origin ) {
			// WordPress' default REST CORS handler reflects arbitrary origins.
			// Remove those credential-bearing headers for this private namespace
			// when the caller is not explicitly allow-listed.
			header_remove( 'Access-Control-Allow-Origin' );
			header_remove( 'Access-Control-Allow-Credentials' );
		}
		return (bool) $served;
	}

	/**
	 * @return string[]
	 */
	private function allowed_origins(): array {
		$home = wp_parse_url( home_url() );
		$list = array();
		if ( ! empty( $home['scheme'] ) && ! empty( $home['host'] ) ) {
			$list[] = $home['scheme'] . '://' . $home['host'];
			$list[] = 'https://app.' . preg_replace( '/^www\./', '', (string) $home['host'] );
		}
		if ( defined( 'BT_OPS_ALLOWED_ORIGINS' ) && is_string( BT_OPS_ALLOWED_ORIGINS ) ) {
			$list = array_merge( $list, array_map( 'trim', explode( ',', BT_OPS_ALLOWED_ORIGINS ) ) );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$list[] = 'http://localhost:5173';
			$list[] = 'http://127.0.0.1:5173';
		}
		$list = array_values( array_unique( array_filter( $list ) ) );
		return apply_filters( 'bt_ops_allowed_origins', $list );
	}
}
