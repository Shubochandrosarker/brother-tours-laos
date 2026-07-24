<?php
/**
 * Activation handler.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Activator {

	public static function activate() {
		G2A_CRM_DB::install();
		G2A_CRM_Permissions::ensure_roles_exist();
		G2A_CRM_Settings::install_defaults();
		G2A_CRM_Message_Templates_Repository::install_defaults();

		update_option( 'g2a_crm_db_version', G2A_CRM_DB_VERSION );
		update_option( 'g2a_crm_activated_at', g2a_crm_now() );

		flush_rewrite_rules();
	}
}
