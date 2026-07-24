<?php
/**
 * Uninstall handler — drops all CRM tables and options.
 *
 * Only runs when an admin deletes the plugin from the WP plugins screen.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'G2A_CRM_KEEP_DATA_ON_UNINSTALL' ) ) {
	define( 'G2A_CRM_KEEP_DATA_ON_UNINSTALL', false );
}

if ( G2A_CRM_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$tables = array(
	'customers',
	'customer_meta',
	'leads',
	'lead_activities',
	'bookings',
	'classes',
	'class_students',
	'memberships',
	'waivers',
	'messages',
	'message_templates',
	'tasks',
	'notes',
	'tags',
	'customer_tags',
	'audit_logs',
	'sensitive_records',
	'integrations',
	'webhooks',
	'settings',
);

foreach ( $tables as $name ) {
	$table = $wpdb->prefix . 'g2a_crm_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'g2a_crm_db_version',
	'g2a_crm_activated_at',
	'g2a_crm_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

foreach ( array( 'crm_owner', 'crm_manager', 'crm_compliance_officer', 'crm_range_staff', 'crm_instructor', 'crm_sales', 'crm_marketing', 'crm_accountant' ) as $role ) {
	remove_role( $role );
}
