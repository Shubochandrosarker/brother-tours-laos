<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Insightistic;

use BrotherTours\OperationsApi\Auth\Csrf;
use Insightistic_GA;
use Insightistic_GSC;
use Insightistic_PageSpeed;
use Insightistic_Sync;
use Insightistic_System_Status;
use Throwable;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

/**
 * Server-side adapter over the Insightistic plugin.
 *
 * Insightistic 4.4.0 is active on this site but registers no REST namespace of
 * its own — it is admin-AJAX only. This controller is the only path by which
 * the dashboard can read GA4, Search Console and PageSpeed data.
 *
 * Signatures verified against Insightistic 4.4.0:
 *   Insightistic_GA::get_sync_payload( $days = 28 )            instance
 *   Insightistic_GA::get_dashboard_data( $days = 28, $force )  instance
 *   Insightistic_GSC::get_sync_payload( $days = 28 )           instance
 *   Insightistic_PageSpeed::get_sync_payload( $url = null )    instance
 *   Insightistic_Sync::last_sync() / logs() / settings()       static
 *   Insightistic_System_Status::collect()                      static
 */
final class AnalyticsController {

	private const CRON_PAGESPEED = 'bt_ops_run_pagespeed';

	/** Google API quota is finite and the dashboard polls. */
	private const TTL_GSC       = HOUR_IN_SECONDS;
	private const TTL_GA4       = HOUR_IN_SECONDS;
	private const TTL_PAGESPEED = 6 * HOUR_IN_SECONDS;
	private const TTL_STATUS    = 5 * MINUTE_IN_SECONDS;

	/**
	 * Option keys that must never appear in a REST response.
	 *
	 * If a new Insightistic integration is added, add its secret key here first.
	 */
	private const FORBIDDEN_KEYS = array(
		'insightistic_api_private_key',
		'insightistic_pagespeed_api_key_enc',
		'insightistic_groq_key',
		'insightistic_connector_secret',
		'insightistic_crypto_secret',
	);

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( self::CRON_PAGESPEED, array( $this, 'cron_pagespeed' ), 10, 2 );
	}

	public function routes(): void {
		foreach (
			array(
				'/analytics/status'         => 'status',
				'/analytics/search-console' => 'search_console',
				'/analytics/ga4'            => 'ga4',
				'/analytics/pagespeed'      => 'pagespeed',
				'/analytics/404s'           => 'not_found_log',
			) as $route => $method
		) {
			register_rest_route(
				BTOA_NAMESPACE,
				$route,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, $method ),
						'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_view_health', false ),
					),
				)
			);
		}

		register_rest_route(
			BTOA_NAMESPACE,
			'/analytics/pagespeed/run',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'run_pagespeed' ),
					'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'manage_options', true ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------- status */

	public function status( WP_REST_Request $request ) {
		$cached = get_transient( 'bt_ops_analytics_status_v2' );
		if ( is_array( $cached ) ) {
			return response( array_merge( $cached, array( 'cached' => true ) ) );
		}

		$active = $this->plugin_active();
		$status = array(
			'insightistic' => array(
				'active'  => $active,
				'version' => defined( 'INSIGHTISTIC_VERSION' ) ? INSIGHTISTIC_VERSION : null,
			),
			/*
			 * Booleans only. Never the values behind them.
			 *
			 * These key names are read from Insightistic 4.4.0 rather than
			 * guessed at, because guessing produced a dashboard that reported
			 * "Not configured" while real GA4 channel data was flowing through
			 * the panel directly underneath it:
			 *
			 *   class-insightistic-auth.php:32-35  api_email + api_private_key
			 *   class-insightistic-ga.php:60       insightistic_property_id
			 *   class-insightistic-gsc.php:45      insightistic_gsc_property_url
			 *
			 * GA4 needs both halves of the service account. A private key with
			 * no client email fails at get_access_token() with missing_creds,
			 * so reporting it as configured would only move the confusion.
			 */
			'ga4'          => array(
				'configured' => $this->has_option( 'insightistic_api_private_key' )
					&& $this->has_option( 'insightistic_api_email' )
					&& $this->has_option( 'insightistic_property_id' ),
				'propertyId' => ( (string) get_option( 'insightistic_property_id', '' ) ) ?: null,
			),
			'gsc'          => array(
				'configured' => $this->has_option( 'insightistic_gsc_property_url' ),
				'property'   => (string) get_option( 'insightistic_gsc_property_url', '' ),
			),
			'pagespeed'    => array(
				'configured' => $this->has_option( 'insightistic_pagespeed_api_key_enc' ),
				'defaultUrl' => $this->default_url(),
			),
			'lastSync'     => $this->call_static( Insightistic_Sync::class, 'last_sync' ),
			'syncLog'      => $this->call_static( Insightistic_Sync::class, 'logs' ),
			'systemStatus' => $this->call_static( Insightistic_System_Status::class, 'collect' ),
			'cached'       => false,
		);

		$status = $this->scrub( $status );
		set_transient( 'bt_ops_analytics_status_v2', $status, self::TTL_STATUS );

		return response( $status );
	}

	/* -------------------------------------------------------- search console */

	public function search_console( WP_REST_Request $request ) {
		$days = $this->days( $request->get_param( 'days' ) );
		$key  = 'bt_ops_gsc_' . $days;

		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return response( $this->sourced( $cached, 'insightistic-gsc', true ) );
		}

		$payload = $this->guard(
			static function () use ( $days ) {
				if ( ! class_exists( Insightistic_GSC::class ) ) {
					return null;
				}
				$gsc = new Insightistic_GSC();
				return $gsc->get_sync_payload( $days );
			}
		);
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( ! is_array( $payload ) ) {
			return error( 'bt_ops_analytics_unavailable', __( 'Search Console data is not available.', 'brother-tours-operations-api' ), 503 );
		}

		$data           = array(
			'days'    => $days,
			'daily'   => $this->rows( $payload, 'daily' ),
			'queries' => array_slice( $this->rows( $payload, 'queries' ), 0, 250 ),
			'pages'   => array_slice( $this->rows( $payload, 'pages' ), 0, 250 ),
		);
		$data['totals'] = $this->totals( $data['daily'] );

		set_transient( $key, $data, self::TTL_GSC );
		return response( $this->sourced( $data, 'insightistic-gsc', false ) );
	}

	/* -------------------------------------------------------------------- ga4 */

	public function ga4( WP_REST_Request $request ) {
		$days = $this->days( $request->get_param( 'days' ) );
		$key  = 'bt_ops_ga4_' . $days;

		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return response( $this->sourced( $cached, 'insightistic-ga4', true ) );
		}

		$sync = $this->guard(
			static function () use ( $days ) {
				if ( ! class_exists( Insightistic_GA::class ) ) {
					return null;
				}
				$ga = new Insightistic_GA();
				return $ga->get_sync_payload( $days );
			}
		);
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}

		// Degrade rather than fail: the sync payload alone is still useful.
		$dashboard = $this->guard(
			static function () use ( $days ) {
				if ( ! class_exists( Insightistic_GA::class ) ) {
					return null;
				}
				$ga = new Insightistic_GA();
				return $ga->get_dashboard_data( $days );
			}
		);
		if ( is_wp_error( $dashboard ) ) {
			$dashboard = null;
		}

		if ( ! is_array( $sync ) && ! is_array( $dashboard ) ) {
			return error( 'bt_ops_analytics_unavailable', __( 'GA4 data is not available.', 'brother-tours-operations-api' ), 503 );
		}

		$daily = $this->rows( is_array( $sync ) ? $sync : array(), 'daily' );

		$data = array(
			'days'           => $days,
			'daily'          => $daily,
			// This property returns channel totals with an empty daily series.
			// Say so explicitly: a client rendering an empty array as a flat line
			// would be claiming zero traffic, which is a different and wrong claim.
			'dailyAvailable' => array() !== $daily,
			'channels'       => $this->rows( is_array( $sync ) ? $sync : array(), 'channels' ),
			'countries'      => is_array( $dashboard ) ? $this->rows( $dashboard, 'countries' ) : array(),
			'pages'          => is_array( $dashboard ) ? $this->rows( $dashboard, 'pages' ) : array(),
			'overview'       => is_array( $dashboard ) ? ( $dashboard['overview'] ?? null ) : null,
			'totals'         => is_array( $dashboard ) ? ( $dashboard['structured_data']['totals'] ?? null ) : null,
		);

		// get_dashboard_data() also returns a pre-rendered `html` string built by
		// another plugin. scrub() drops it, so it cannot reach the React tree
		// even by accident.
		$data = $this->scrub( $data );

		set_transient( $key, $data, self::TTL_GA4 );
		return response( $this->sourced( $data, 'insightistic-ga4', false ) );
	}

	/* -------------------------------------------------------------- pagespeed */

	public function pagespeed( WP_REST_Request $request ) {
		$url = $this->target_url( $request->get_param( 'url' ) );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$strategy = 'desktop' === $request->get_param( 'strategy' ) ? 'desktop' : 'mobile';
		$key      = $this->psi_key( $url, $strategy );

		$fresh = get_transient( $key );
		if ( is_array( $fresh ) ) {
			return response(
				array(
					'status'    => 'fresh',
					'url'       => $url,
					'strategy'  => $strategy,
					'data'      => $fresh['data'] ?? null,
					'fetchedAt' => $fresh['fetchedAt'] ?? null,
				)
			);
		}

		// The last result outlives the transient so the UI can say "stale"
		// instead of dropping back to "never run" every six hours.
		$last = get_option( $key . '_last' );
		if ( is_array( $last ) ) {
			return response(
				array(
					'status'    => 'stale',
					'url'       => $url,
					'strategy'  => $strategy,
					'data'      => $last['data'] ?? null,
					'fetchedAt' => $last['fetchedAt'] ?? null,
				)
			);
		}

		return response( array( 'status' => 'never_run', 'url' => $url, 'strategy' => $strategy, 'data' => null, 'fetchedAt' => null ) );
	}

	public function run_pagespeed( WP_REST_Request $request ) {
		$url = $this->target_url( $request->get_param( 'url' ) );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		if ( ! $this->has_option( 'insightistic_pagespeed_api_key_enc' ) ) {
			return error( 'bt_ops_analytics_unavailable', __( 'PageSpeed is not configured in Insightistic.', 'brother-tours-operations-api' ), 503 );
		}

		$strategy = 'desktop' === $request->get_param( 'strategy' ) ? 'desktop' : 'mobile';

		// A live PSI call is routinely 10-30s and would hit the PHP timeout
		// inside a dashboard request. Schedule it; the client polls.
		if ( ! wp_next_scheduled( self::CRON_PAGESPEED, array( $url, $strategy ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_PAGESPEED, array( $url, $strategy ) );
		}

		return response( array( 'status' => 'queued', 'url' => $url, 'strategy' => $strategy, 'queuedAt' => gmdate( 'c' ) ), 202 );
	}

	/**
	 * Cron worker — runs outside the request cycle, so a slow Google call costs
	 * nothing anyone is waiting on.
	 */
	public function cron_pagespeed( string $url, string $strategy = 'mobile' ): void {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return;
		}

		$payload = $this->guard(
			static function () use ( $url ) {
				if ( ! class_exists( Insightistic_PageSpeed::class ) ) {
					return null;
				}
				$psi = new Insightistic_PageSpeed();
				return $psi->get_sync_payload( $url );
			}
		);
		if ( is_wp_error( $payload ) || ! is_array( $payload ) ) {
			return;
		}

		$record = array( 'data' => $this->scrub( $payload ), 'fetchedAt' => gmdate( 'c' ) );
		$key    = $this->psi_key( $url, $strategy );
		set_transient( $key, $record, self::TTL_PAGESPEED );
		update_option( $key . '_last', $record, false );
	}

	/* ------------------------------------------------------------------ 404s */

	public function not_found_log( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 25 ) ) );

		$log = get_option( 'insightistic_404_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$rows = array();
		foreach ( $log as $key => $entry ) {
			if ( is_array( $entry ) ) {
				$rows[] = array(
					'url'      => (string) ( $entry['url'] ?? $entry['uri'] ?? $key ),
					'hits'     => (int) ( $entry['hits'] ?? $entry['count'] ?? 1 ),
					'lastSeen' => isset( $entry['last'] ) ? (string) $entry['last'] : ( isset( $entry['last_seen'] ) ? (string) $entry['last_seen'] : null ),
				);
				continue;
			}
			$rows[] = array( 'url' => (string) $key, 'hits' => (int) $entry, 'lastSeen' => null );
		}

		usort( $rows, static fn( array $a, array $b ) => $b['hits'] <=> $a['hits'] );

		$total = count( $rows );

		// The log is a single ~41 KB option. Slice server-side; never ship the blob.
		return response(
			array(
				'items'      => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
				'total'      => $total,
				'page'       => $page,
				'perPage'    => $per_page,
				'totalPages' => (int) max( 1, (int) ceil( $total / $per_page ) ),
			)
		);
	}

	/* --------------------------------------------------------------- helpers */

	/**
	 * Every call into Insightistic goes through here.
	 *
	 * The plugin can be deactivated at any time and its internals are not a
	 * public API. A failure must degrade the analytics page, never 500 the
	 * dashboard.
	 *
	 * @return mixed|\WP_Error|null
	 */
	private function guard( callable $fn ) {
		if ( ! $this->plugin_active() ) {
			return null;
		}
		try {
			return $fn();
		} catch ( Throwable $e ) {
			// Message only. A trace can carry option values.
			return error( 'bt_ops_analytics_unavailable', $e->getMessage(), 503 );
		}
	}

	/** @return mixed|null */
	private function call_static( string $class, string $method ) {
		$result = $this->guard(
			static function () use ( $class, $method ) {
				if ( ! class_exists( $class ) || ! is_callable( array( $class, $method ) ) ) {
					return null;
				}
				return call_user_func( array( $class, $method ) );
			}
		);
		return is_wp_error( $result ) ? null : $result;
	}

	private function plugin_active(): bool {
		return class_exists( Insightistic_GA::class )
			|| class_exists( Insightistic_GSC::class )
			|| defined( 'INSIGHTISTIC_VERSION' );
	}

	private function has_option( string $key ): bool {
		$value = get_option( $key, '' );
		return ! ( '' === $value || null === $value || false === $value );
	}

	private function default_url(): string {
		$configured = (string) get_option( 'insightistic_pagespeed_default_url', '' );
		return '' !== $configured ? $configured : home_url( '/' );
	}

	private function psi_key( string $url, string $strategy ): string {
		return 'bt_ops_psi_' . md5( $url . '|' . $strategy );
	}

	private function days( $days ): int {
		return max( 1, min( 90, (int) ( $days ?: 28 ) ) );
	}

	/**
	 * PageSpeed targets are same-origin only.
	 *
	 * Without this the endpoint is an open PSI relay: anyone with a session
	 * could point Google's crawler at an arbitrary host on our API key.
	 *
	 * @return string|\WP_Error
	 */
	private function target_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return $this->default_url();
		}
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return error( 'bt_ops_invalid_url', __( 'Unparseable URL.', 'brother-tours-operations-api' ), 422 );
		}
		$target = wp_parse_url( $url, PHP_URL_HOST );
		$home   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $target ) || ! is_string( $home ) ) {
			return error( 'bt_ops_invalid_url', __( 'Unparseable URL.', 'brother-tours-operations-api' ), 422 );
		}
		// Strip the www prefix specifically — ltrim with 'www.' would treat it as
		// a character set and eat leading w/. from any host.
		$strip = static fn( string $host ): string => (string) preg_replace( '/^www\./i', '', strtolower( $host ) );
		if ( $strip( $target ) !== $strip( $home ) ) {
			return error( 'bt_ops_url_not_allowed', __( 'PageSpeed can only be run against this site.', 'brother-tours-operations-api' ), 422 );
		}
		return $url;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<int,mixed>
	 */
	private function rows( array $payload, string $key ): array {
		$rows = $payload[ $key ] ?? array();
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * @param array<int,mixed> $daily
	 * @return array<string,mixed>
	 */
	private function totals( array $daily ): array {
		$clicks       = 0;
		$impressions  = 0;
		$position_sum = 0.0;
		$position_n   = 0;
		foreach ( $daily as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clicks      += (int) ( $row['clicks'] ?? 0 );
			$impressions += (int) ( $row['impressions'] ?? 0 );
			if ( isset( $row['avg_position'] ) ) {
				$position_sum += (float) $row['avg_position'];
				++$position_n;
			}
		}
		return array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? round( $clicks / $impressions, 4 ) : 0.0,
			'avgPosition' => $position_n > 0 ? round( $position_sum / $position_n, 2 ) : null,
		);
	}

	/**
	 * Last line of defence before serialisation.
	 *
	 * Drops any key matching a known secret at any depth. The handlers above
	 * already avoid reading them; this catches an upstream Insightistic payload
	 * embedding one we did not anticipate.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function scrub( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$clean = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) ) {
				$lower = strtolower( $key );
				if ( in_array( $lower, self::FORBIDDEN_KEYS, true ) ) {
					continue;
				}
				if ( 1 === preg_match( '/(private_key|api_key|secret|_enc$|password|token)/', $lower ) ) {
					continue;
				}
				if ( 'html' === $lower ) {
					continue; // Pre-rendered markup never crosses the boundary.
				}
			}
			$clean[ $key ] = is_array( $item ) ? $this->scrub( $item ) : $item;
		}
		return $clean;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function sourced( array $data, string $source, bool $cached ): array {
		return array_merge( $data, array( 'source' => $source, 'cached' => $cached, 'dataGeneratedAt' => gmdate( 'c' ) ) );
	}
}
