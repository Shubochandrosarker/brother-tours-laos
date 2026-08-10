<?php
/**
 * Plugin Name:       Brother Tours Operations API
 * Plugin URI:        https://brothertours.com/
 * Description:       Secure REST operations bridge for the Brother Tours Horizons management app. Reuses WPistic Tour Manager, Formistic, and WordPress as the source of truth.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       brother-tours-operations-api
 *
 * @package BrotherToursOperationsApi
 */

declare(strict_types=1);

namespace BrotherTours\OperationsApi;

use WP_Error;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BTOA_VERSION', '1.0.0' );
define( 'BTOA_FILE', __FILE__ );
define( 'BTOA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BTOA_NAMESPACE', 'bt-ops/v1' );
define( 'BTOA_SESSION_COOKIE', 'bt_ops_session' );
define( 'BTOA_SESSION_TTL', 12 * HOUR_IN_SECONDS );

/**
 * Tiny PSR-4-style autoloader. Composer is intentionally not required.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = BTOA_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Standard success envelope used by the Horizons app.
 *
 * @param mixed               $data Payload.
 * @param int                 $status HTTP status.
 * @param array<string,mixed> $meta Additional metadata.
 */
function response( mixed $data, int $status = 200, array $meta = array() ): WP_REST_Response {
	$default_meta = array(
		'generatedAt' => gmdate( 'c' ),
		'timezone'    => wp_timezone_string(),
		'apiVersion'  => BTOA_VERSION,
	);

	return new WP_REST_Response(
		array(
			'success' => true,
			'data'    => $data,
			'meta'    => array_merge( $default_meta, $meta ),
		),
		$status
	);
}

/**
 * Standard REST error.
 *
 * @param array<string,mixed> $details Optional safe details.
 */
function error( string $code, string $message, int $status = 400, array $details = array() ): WP_Error {
	return new WP_Error(
		$code,
		$message,
		array_merge(
			array(
				'status'    => $status,
				'requestId' => wp_generate_uuid4(),
			),
			$details
		)
	);
}

/**
 * Check whether a WordPress table exists.
 */
function table_exists( string $table ): bool {
	global $wpdb;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $found === $table;
}

/**
 * Boot after the dependency plugins have loaded their classes.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( PHP_VERSION_ID < 80100 ) {
			return;
		}

		( new Auth\SessionController() )->register();
		( new Dashboard\DashboardController() )->register();
		( new Tours\ToursController() )->register();
		( new Tours\DestinationsController() )->register();
		( new Tours\ExperiencesController() )->register();
		( new Tours\DeparturesController() )->register();
		( new Bookings\BookingsController() )->register();
		( new Bookings\BookingActionsController() )->register();
		( new Formistic\InboxController() )->register();
		( new Connections\ConnectionsController() )->register();
		( new Reports\ReportsController() )->register();
		( new Team\TeamController() )->register();
		( new System\HealthController() )->register();
	},
	30
);

/**
 * Dependency warning only. The plugin remains loadable so /system/health can
 * report what is unavailable instead of taking the site down.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$missing = array();
		if ( ! class_exists( '\\Wpistic\\TourManager\\Booking\\BookingService' ) ) {
			$missing[] = 'WPistic Tour Manager 2.0+';
		}
		if ( ! class_exists( '\\Wpistic_Formistic_Database' ) ) {
			$missing[] = 'Formistic 2.0+';
		}
		if ( $missing ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Brother Tours Operations API:', 'brother-tours-operations-api' ) . '</strong> ';
			echo esc_html( sprintf( __( 'Some API modules are unavailable because these dependencies are missing: %s', 'brother-tours-operations-api' ), implode( ', ', $missing ) ) );
			echo '</p></div>';
		}
	}
);
