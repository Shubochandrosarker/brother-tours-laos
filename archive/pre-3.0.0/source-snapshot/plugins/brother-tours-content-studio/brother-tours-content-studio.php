<?php
/**
 * Plugin Name:       Brother Tours Content Studio
 * Plugin URI:        https://brothertours.com/
 * Description:       Gutenberg-first, structured content and visual editing system for Brother Tours.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       brother-tours-content-studio
 * Domain Path:       /languages
 *
 * @package BrotherToursContentStudio
 */

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BT_CS_VERSION', '1.0.2' );
define( 'BT_CS_FILE', __FILE__ );
define( 'BT_CS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BT_CS_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = BT_CS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		Capabilities::activate();
		Settings::activate();
		Sitemap::activate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Bootstrap::instance()->boot();
	},
	21
);
