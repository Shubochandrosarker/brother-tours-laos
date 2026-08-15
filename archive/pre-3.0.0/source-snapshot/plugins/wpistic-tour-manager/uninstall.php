<?php
/**
 * Uninstall. Removes options. Tables are kept unless the operator explicitly opted
 * into data deletion (wpistic_tm_delete_data), so removing the plugin never silently
 * destroys bookings.
 *
 * @package WpisticTourManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wpistic_tm_options = array(
	'wpistic_tm_currency',
	'wpistic_tm_deposit_type',
	'wpistic_tm_deposit_value',
	'wpistic_tm_pay_to_hold',
	'wpistic_tm_from_email',
	'wpistic_tm_db_version',
	'wpistic_tm_flush',
	'wpistic_tm_stripe',
	'wpistic_tm_paypal',
	'wpistic_tm_bank',
	'wpistic_tm_binance',
);

foreach ( $wpistic_tm_options as $wpistic_tm_option ) {
	delete_option( $wpistic_tm_option );
}

if ( get_option( 'wpistic_tm_delete_data' ) ) {
	global $wpdb;
	$wpistic_tm_prefix = $wpdb->prefix . 'wpistic_';
	foreach ( array( 'bookings', 'transactions', 'webhook_events', 'audit_log', 'connections', 'connection_log' ) as $wpistic_tm_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpistic_tm_prefix}{$wpistic_tm_table}" ); // phpcs:ignore WordPress.DB
	}
	delete_option( 'wpistic_tm_delete_data' );
}
