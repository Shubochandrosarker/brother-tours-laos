<?php
/**
 * Deactivation handler. Intentionally non-destructive — data stays.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Deactivator {

	public static function deactivate() {
		G2A_CRM_Reminder_Scheduler::unschedule();
		G2A_CRM_Message_Dispatcher::unschedule();
		wp_clear_scheduled_hook( G2A_CRM_Webhook_Dispatcher::CRON_HOOK );
		flush_rewrite_rules();
	}
}
