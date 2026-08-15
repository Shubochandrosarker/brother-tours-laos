<?php
/**
 * Plugin Name:       WPistic Tour Manager
 * Plugin URI:        https://wordpressistic.com/wpistic-tour-manager
 * Description:       Premium tour, booking, deposit and payment system for Brother Tours. Framework-agnostic core (packages/tour-core) + WordPress adapter.
 * Version:           3.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com
 * License:           GPL-2.0-or-later
 * Text Domain:       wpistic-tour-manager
 * Domain Path:       /languages
 *
 * @package WpisticTourManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPISTIC_TM_VERSION', '3.0.0' );
// Schema version intentionally NOT bumped to 2.0.0: v2.0.0 of the suite made
// no Tour Manager table/column changes, so bumping this would trigger a
// no-op "upgrade" and misrepresent what actually changed. Bump only when
// Install/Tables.php's schema itself changes.
define( 'WPISTIC_TM_DB_VERSION', '1.1.0' );
define( 'WPISTIC_TM_FILE', __FILE__ );
define( 'WPISTIC_TM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPISTIC_TM_URL', plugin_dir_url( __FILE__ ) );

require_once WPISTIC_TM_DIR . 'src/autoload.php';

register_activation_hook( __FILE__, array( '\\Wpistic\\TourManager\\Install\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\Wpistic\\TourManager\\Install\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Wpistic\TourManager\Plugin::instance()->boot();
	}
);
