<?php
/**
 * Plugin Name:       Brother Tours Resource Downloads
 * Plugin URI:        https://brothertours.com/
 * Description:       One reusable download-resource system: a single popup controller, an inline CTA shortcode, and a resource registry shared by every landing page.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       brother-tours-resource-downloads
 *
 * @package BrotherTours\ResourceDownloads
 */

declare(strict_types=1);

namespace BrotherTours\ResourceDownloads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BTRD_VERSION', '1.0.0' );
define( 'BTRD_FILE', __FILE__ );
define( 'BTRD_DIR', plugin_dir_path( __FILE__ ) );
define( 'BTRD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Tiny PSR-4-style autoloader, matching the convention the Operations API uses.
 * Composer is intentionally not required.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = BTRD_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( PHP_VERSION_ID < 80100 ) {
			return;
		}
		( new Assets() )->register();
		( new Shortcode() )->register();
	},
	20
);
