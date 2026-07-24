<?php
/**
 * Plugin Name:       WPistic CRM
 * Plugin URI:        https://wordpressistic.com
 * Description:       The WPistic CRM — a complete customer, lead, booking and communications dashboard. Built by WordPressistic and configured to work hand-in-hand with the WPistic Custom Theme and WPistic Contact form system.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       guns2ammo-crm
 * Domain Path:       /languages
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'G2A_CRM_VERSION', '1.0.0' );
define( 'G2A_CRM_DB_VERSION', '9' );
define( 'G2A_CRM_PLUGIN_FILE', __FILE__ );
define( 'G2A_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'G2A_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'G2A_CRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'G2A_CRM_REST_NAMESPACE', 'g2a-crm/v1' );

require_once G2A_CRM_PLUGIN_DIR . 'includes/helpers.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-db.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-permissions.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-audit-log.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-settings.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-activator.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/customers/class-customers-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/leads/class-leads-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/tasks/class-tasks-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/bookings/class-bookings-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/waivers/class-waivers-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/memberships/class-memberships-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/classes/class-classes-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/classes/class-class-students-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/messages/class-messages-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/messages/class-message-templates-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/tags/class-tags-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/notes/class-notes-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/sensitive/class-sensitive-records-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/webhooks/class-webhooks-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/webhooks/class-webhook-deliveries-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/integrations/class-integrations-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/modules/suppressions/class-suppressions-repository.php';
require_once G2A_CRM_PLUGIN_DIR . 'public/class-unsubscribe.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-waiver-view-service.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-message-renderer.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-email-sender.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-sms-sender.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-message-dispatcher.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-reminder-scheduler.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-reports-service.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-csv-exporter.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-webhook-dispatcher.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-customer-merger.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-lead-auto-assigner.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-woocommerce-sync.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-woocommerce-subscriptions-sync.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-bulk-actions.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/services/class-saved-views.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-rest-base-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-customers-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-leads-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-tasks-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-reports-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-audit-logs-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-bookings-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-waivers-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-memberships-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-classes-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-messages-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-message-templates-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-tags-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-notes-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-settings-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-sensitive-records-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-webhooks-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-integrations-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-bulk-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/rest/class-suppressions-controller.php';
require_once G2A_CRM_PLUGIN_DIR . 'public/class-kiosk.php';
require_once G2A_CRM_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once G2A_CRM_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'G2A_CRM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'G2A_CRM_Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		G2A_CRM_Plugin::instance()->boot();
	}
);

// Declare compatibility with WooCommerce High-Performance Order Storage
// (custom order tables). The CRM only reads Woo orders via the documented
// CRUD API (wc_get_order, $order->get_*) so it works under both legacy
// post storage and HPOS.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
