<?php
/**
 * Database schema installer. Idempotent — safe to run repeatedly via dbDelta().
 *
 * Phase 1 tables marked PHASE 1. Later tables are scaffolded now so they exist
 * the moment the corresponding modules ship; nothing references them yet.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_DB {

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix . 'g2a_crm_';

		$schema = array();

		// PHASE 1: customers (+ Phase 4 opt-in columns added via dbDelta).
		$schema[] = "CREATE TABLE {$p}customers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NULL,
			first_name VARCHAR(100) NOT NULL DEFAULT '',
			last_name VARCHAR(100) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(50) NOT NULL DEFAULT '',
			address_line_1 VARCHAR(190) NOT NULL DEFAULT '',
			address_line_2 VARCHAR(190) NOT NULL DEFAULT '',
			city VARCHAR(100) NOT NULL DEFAULT '',
			state VARCHAR(100) NOT NULL DEFAULT '',
			zip VARCHAR(30) NOT NULL DEFAULT '',
			customer_type VARCHAR(50) NOT NULL DEFAULT 'walk_in',
			status VARCHAR(50) NOT NULL DEFAULT 'active',
			source VARCHAR(100) NOT NULL DEFAULT '',
			preferred_contact VARCHAR(50) NOT NULL DEFAULT 'email',
			waiver_status VARCHAR(50) NOT NULL DEFAULT 'not_submitted',
			membership_status VARCHAR(50) NOT NULL DEFAULT 'none',
			email_opt_in TINYINT(1) NOT NULL DEFAULT 1,
			sms_opt_in TINYINT(1) NOT NULL DEFAULT 1,
			email_opt_out_at DATETIME NULL,
			sms_opt_out_at DATETIME NULL,
			date_of_birth DATE NULL,
			notes_summary TEXT NULL,
			last_activity_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY wp_user_id (wp_user_id),
			KEY email (email),
			KEY phone (phone),
			KEY status (status),
			KEY customer_type (customer_type),
			KEY last_activity_at (last_activity_at)
		) {$charset_collate};";

		// PHASE 1: customer meta (key/value side table for extensible fields).
		$schema[] = "CREATE TABLE {$p}customer_meta (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			meta_key VARCHAR(190) NOT NULL,
			meta_value LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY meta_key (meta_key)
		) {$charset_collate};";

		// PHASE 1: leads.
		$schema[] = "CREATE TABLE {$p}leads (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(50) NOT NULL DEFAULT '',
			interest_type VARCHAR(100) NOT NULL DEFAULT 'general',
			source VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(50) NOT NULL DEFAULT 'new',
			assigned_to BIGINT UNSIGNED NULL,
			lead_score INT NOT NULL DEFAULT 0,
			next_follow_up_at DATETIME NULL,
			last_contacted_at DATETIME NULL,
			conversion_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			lost_reason TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY interest_type (interest_type),
			KEY next_follow_up_at (next_follow_up_at)
		) {$charset_collate};";

		// PHASE 1: lead activities (timeline events for a lead).
		$schema[] = "CREATE TABLE {$p}lead_activities (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			actor_id BIGINT UNSIGNED NULL,
			activity_type VARCHAR(50) NOT NULL,
			summary VARCHAR(255) NOT NULL DEFAULT '',
			body LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY activity_type (activity_type)
		) {$charset_collate};";

		// PHASE 1: tasks.
		$schema[] = "CREATE TABLE {$p}tasks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			related_type VARCHAR(50) NOT NULL DEFAULT '',
			related_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(190) NOT NULL,
			description TEXT NULL,
			assigned_to BIGINT UNSIGNED NULL,
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			due_at DATETIME NULL,
			completed_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			completed_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY related (related_type, related_id),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY due_at (due_at)
		) {$charset_collate};";

		// PHASE 1: notes (free-form notes on any record).
		$schema[] = "CREATE TABLE {$p}notes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			related_type VARCHAR(50) NOT NULL,
			related_id BIGINT UNSIGNED NOT NULL,
			body LONGTEXT NOT NULL,
			is_internal TINYINT(1) NOT NULL DEFAULT 1,
			author_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY related (related_type, related_id),
			KEY author_id (author_id)
		) {$charset_collate};";

		// PHASE 1: tags.
		$schema[] = "CREATE TABLE {$p}tags (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(100) NOT NULL,
			name VARCHAR(100) NOT NULL,
			color VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		// PHASE 1: customer_tags (many-to-many).
		$schema[] = "CREATE TABLE {$p}customer_tags (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			tag_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY customer_tag (customer_id, tag_id),
			KEY tag_id (tag_id)
		) {$charset_collate};";

		// PHASE 1: audit logs.
		$schema[] = "CREATE TABLE {$p}audit_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_id BIGINT UNSIGNED NULL,
			action VARCHAR(190) NOT NULL,
			object_type VARCHAR(100) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			old_value LONGTEXT NULL,
			new_value LONGTEXT NULL,
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			user_agent TEXT NULL,
			reason TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY actor_id (actor_id),
			KEY object (object_type, object_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		// PHASE 1: settings (key/value store separate from wp_options for big payloads).
		$schema[] = "CREATE TABLE {$p}settings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key VARCHAR(190) NOT NULL,
			setting_value LONGTEXT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";

		// PHASE 2+: scaffold tables so future phases plug in cleanly.
		$schema[] = "CREATE TABLE {$p}bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			booking_type VARCHAR(100) NOT NULL,
			resource_type VARCHAR(100) NOT NULL DEFAULT '',
			resource_id BIGINT UNSIGNED NULL,
			start_time DATETIME NOT NULL,
			end_time DATETIME NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'pending',
			payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			staff_id BIGINT UNSIGNED NULL,
			external_ref VARCHAR(190) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY booking_type (booking_type),
			KEY status (status),
			KEY start_time (start_time)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}classes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			class_type VARCHAR(100) NOT NULL DEFAULT '',
			instructor_id BIGINT UNSIGNED NULL,
			start_time DATETIME NOT NULL,
			end_time DATETIME NULL,
			capacity INT NOT NULL DEFAULT 0,
			price DECIMAL(10,2) NOT NULL DEFAULT 0,
			required_waiver TINYINT(1) NOT NULL DEFAULT 1,
			status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY start_time (start_time),
			KEY class_type (class_type)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}class_students (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'registered',
			payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
			attended TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY class_customer (class_id, customer_id),
			KEY customer_id (customer_id)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}memberships (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			plan_name VARCHAR(190) NOT NULL,
			access_level VARCHAR(50) NOT NULL DEFAULT 'standard',
			status VARCHAR(50) NOT NULL DEFAULT 'active',
			start_date DATE NOT NULL,
			renewal_date DATE NULL,
			cancelled_at DATETIME NULL,
			billing_status VARCHAR(50) NOT NULL DEFAULT 'current',
			monthly_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			payment_method_ref VARCHAR(190) NOT NULL DEFAULT '',
			cancellation_reason TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY renewal_date (renewal_date)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}waivers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			waiver_type VARCHAR(100) NOT NULL DEFAULT 'range',
			status VARCHAR(50) NOT NULL DEFAULT 'submitted',
			signed_at DATETIME NULL,
			expires_at DATETIME NULL,
			pdf_url TEXT NULL,
			signature_hash VARCHAR(190) NOT NULL DEFAULT '',
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			device_info TEXT NULL,
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}messages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NULL,
			lead_id BIGINT UNSIGNED NULL,
			channel VARCHAR(50) NOT NULL,
			direction VARCHAR(50) NOT NULL DEFAULT 'outbound',
			template_id BIGINT UNSIGNED NULL,
			subject VARCHAR(190) NOT NULL DEFAULT '',
			body LONGTEXT NOT NULL,
			rendered_subject VARCHAR(190) NOT NULL DEFAULT '',
			rendered_body LONGTEXT NULL,
			to_address VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(50) NOT NULL DEFAULT 'queued',
			dedup_key VARCHAR(190) NOT NULL DEFAULT '',
			provider VARCHAR(50) NOT NULL DEFAULT '',
			provider_message_id VARCHAR(190) NOT NULL DEFAULT '',
			attempt_count INT NOT NULL DEFAULT 0,
			next_attempt_at DATETIME NULL,
			sent_by BIGINT UNSIGNED NULL,
			sent_at DATETIME NULL,
			error_text TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY lead_id (lead_id),
			KEY channel (channel),
			KEY status (status),
			KEY dedup_key (dedup_key),
			KEY status_next (status, next_attempt_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}message_templates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(100) NOT NULL,
			channel VARCHAR(50) NOT NULL DEFAULT 'email',
			subject VARCHAR(190) NOT NULL DEFAULT '',
			body LONGTEXT NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		// Restricted compliance-sensitive records — separated from main CRM tables on purpose.
		$schema[] = "CREATE TABLE {$p}sensitive_records (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			record_type VARCHAR(100) NOT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'open',
			reference_code VARCHAR(190) NOT NULL DEFAULT '',
			document_ref TEXT NULL,
			body LONGTEXT NULL,
			checklist LONGTEXT NULL,
			handled_by BIGINT UNSIGNED NULL,
			closed_at DATETIME NULL,
			closed_reason TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY record_type (record_type),
			KEY status (status),
			KEY handled_by (handled_by)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}integrations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(100) NOT NULL,
			external_type VARCHAR(100) NOT NULL,
			external_id VARCHAR(190) NOT NULL,
			local_type VARCHAR(100) NOT NULL,
			local_id BIGINT UNSIGNED NOT NULL,
			payload LONGTEXT NULL,
			last_synced_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY external (provider, external_type, external_id),
			KEY local (local_type, local_id)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$p}webhooks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			event VARCHAR(100) NOT NULL,
			target_url TEXT NOT NULL,
			secret VARCHAR(190) NOT NULL DEFAULT '',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			last_status VARCHAR(50) NOT NULL DEFAULT '',
			last_sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event (event),
			KEY is_active (is_active)
		) {$charset_collate};";

		// Delivery attempts for outbound webhooks. One row per (webhook x event)
		// kept for retry, debugging, and the deliveries panel in the UI.
		$schema[] = "CREATE TABLE {$p}webhook_deliveries (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			webhook_id BIGINT UNSIGNED NOT NULL,
			event VARCHAR(100) NOT NULL,
			target_url TEXT NOT NULL,
			payload LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			response_code SMALLINT UNSIGNED NULL,
			response_body TEXT NULL,
			attempt_count INT NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			next_attempt_at DATETIME NULL,
			delivered_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY webhook_id (webhook_id),
			KEY status_next (status, next_attempt_at),
			KEY event (event)
		) {$charset_collate};";

		// Suppression list. The dispatcher checks this before every send, and
		// hard-bounce / complaint / unsubscribe events feed rows in. Lookup is
		// per (channel, normalized_address); we store the normalized form so
		// "Joe@Example.com" and "joe@example.com" can't bypass the gate.
		$schema[] = "CREATE TABLE {$p}suppressions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			channel VARCHAR(10) NOT NULL,
			address VARCHAR(190) NOT NULL,
			reason VARCHAR(255) NOT NULL DEFAULT '',
			source VARCHAR(40) NOT NULL DEFAULT 'manual',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY channel_address (channel, address),
			KEY source (source)
		) {$charset_collate};";

		foreach ( $schema as $statement ) {
			dbDelta( $statement );
		}
	}
}
