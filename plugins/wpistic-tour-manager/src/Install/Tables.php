<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Install;

/**
 * Custom tables. Created with dbDelta on activation; minimal PII, full audit trail.
 */
final class Tables {

	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'wpistic_';

		$schemas = array();

		$schemas[] = "CREATE TABLE {$prefix}bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			reference VARCHAR(32) NOT NULL,
			type VARCHAR(24) NOT NULL DEFAULT 'inquiry',
			status VARCHAR(24) NOT NULL DEFAULT 'inquiry',
			portal_status VARCHAR(24) NOT NULL DEFAULT 'new',
			tour_id BIGINT UNSIGNED NULL,
			departure_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(191) NULL,
			customer_email VARCHAR(191) NULL,
			customer_phone VARCHAR(64) NULL,
			customer_country VARCHAR(96) NULL,
			party_adults SMALLINT UNSIGNED NULL,
			party_children VARCHAR(120) NULL,
			hotel_pref VARCHAR(48) NULL,
			addons LONGTEXT NULL,
			special_requests LONGTEXT NULL,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			price_amount VARCHAR(32) NULL,
			deposit_amount VARCHAR(32) NULL,
			balance_amount VARCHAR(32) NULL,
			assigned_to BIGINT UNSIGNED NULL,
			source_url VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY status (status),
			KEY portal_status (portal_status),
			KEY tour_id (tour_id)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			gateway VARCHAR(32) NOT NULL,
			gateway_txn_id VARCHAR(191) NULL,
			type VARCHAR(16) NOT NULL DEFAULT 'deposit',
			amount VARCHAR(32) NOT NULL,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			idempotency_key VARCHAR(64) NULL,
			payload_hash VARCHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY gateway_txn_id (gateway_txn_id)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}webhook_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			gateway VARCHAR(32) NOT NULL,
			event_id VARCHAR(191) NOT NULL,
			received_at DATETIME NOT NULL,
			processed TINYINT(1) NOT NULL DEFAULT 0,
			payload_hash VARCHAR(64) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY gateway_event (gateway, event_id)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_type VARCHAR(32) NOT NULL,
			object_id BIGINT UNSIGNED NULL,
			action VARCHAR(64) NOT NULL,
			actor VARCHAR(96) NULL,
			detail LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type, object_id)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}connections (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(120) NOT NULL,
			type VARCHAR(24) NOT NULL DEFAULT 'webhook',
			target_url VARCHAR(255) NULL,
			secret VARCHAR(191) NULL,
			events LONGTEXT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}connection_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			connection_id BIGINT UNSIGNED NOT NULL,
			event VARCHAR(64) NOT NULL,
			status_code SMALLINT NULL,
			response LONGTEXT NULL,
			attempts SMALLINT NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY connection_id (connection_id)
		) {$charset};";

		foreach ( $schemas as $schema ) {
			dbDelta( $schema );
		}
	}
}
