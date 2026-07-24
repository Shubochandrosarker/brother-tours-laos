<?php
/**
 * Main plugin orchestrator.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class G2A_CRM_Plugin {

	private static $instance = null;

	private $booted = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'guns2ammo-crm', false, dirname( G2A_CRM_PLUGIN_BASENAME ) . '/languages' );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			G2A_CRM_Admin_Menu::instance()->boot();
		}

		add_action( 'init', array( 'G2A_CRM_Permissions', 'ensure_roles_exist' ) );

		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );

		// Public-facing services.
		G2A_CRM_Waiver_View_Service::boot();
		G2A_CRM_Kiosk::boot();
		G2A_CRM_Unsubscribe::boot();

		// Cron scheduling.
		G2A_CRM_Reminder_Scheduler::boot();
		G2A_CRM_Message_Dispatcher::boot();
		G2A_CRM_Webhook_Dispatcher::boot();
		G2A_CRM_WooCommerce_Sync::boot();
	}

	public function register_rest_routes() {
		( new G2A_CRM_Customers_Controller() )->register_routes();
		( new G2A_CRM_Leads_Controller() )->register_routes();
		( new G2A_CRM_Tasks_Controller() )->register_routes();
		( new G2A_CRM_Reports_Controller() )->register_routes();
		( new G2A_CRM_Audit_Logs_Controller() )->register_routes();
		( new G2A_CRM_Bookings_Controller() )->register_routes();
		( new G2A_CRM_Waivers_Controller() )->register_routes();
		( new G2A_CRM_Memberships_Controller() )->register_routes();
		( new G2A_CRM_Classes_Controller() )->register_routes();
		( new G2A_CRM_Messages_Controller() )->register_routes();
		( new G2A_CRM_Message_Templates_Controller() )->register_routes();
		( new G2A_CRM_Tags_Controller() )->register_routes();
		( new G2A_CRM_Notes_Controller() )->register_routes();
		( new G2A_CRM_Settings_Controller() )->register_routes();
		( new G2A_CRM_Sensitive_Records_Controller() )->register_routes();
		( new G2A_CRM_Webhooks_Controller() )->register_routes();
		( new G2A_CRM_Integrations_Controller() )->register_routes();
		( new G2A_CRM_Bulk_Controller() )->register_routes();
		( new G2A_CRM_Suppressions_Controller() )->register_routes();
	}

	public function maybe_upgrade_db() {
		$installed = get_option( 'g2a_crm_db_version' );
		if ( $installed === G2A_CRM_DB_VERSION ) {
			return;
		}
		G2A_CRM_DB::install();
		G2A_CRM_Message_Templates_Repository::install_defaults();
		update_option( 'g2a_crm_db_version', G2A_CRM_DB_VERSION );
	}
}
