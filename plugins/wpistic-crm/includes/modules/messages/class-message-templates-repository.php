<?php
/**
 * Message templates data access layer.
 *
 * Slug is the lookup key staff and cron tasks use ("booking_reminder",
 * "waiver_expiry", "membership_renewal", etc.).
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Message_Templates_Repository {

	const CHANNELS = array( 'email', 'sms' );

	public static function table() {
		return g2a_crm_table( 'message_templates' );
	}

	public static function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_by_slug( $slug ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE slug = %s LIMIT 1', sanitize_key( $slug ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function list( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 50;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['channel'] ) ) {
			$where[]    = 'channel = %s';
			$prepared[] = sanitize_key( $args['channel'] );
		}
		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$where[]    = 'is_active = %d';
			$prepared[] = $args['is_active'] ? 1 : 0;
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $prepared ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $prepared ) : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY slug ASC LIMIT %d OFFSET %d",
				array_merge( $prepared, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
		);
	}

	public static function upsert( array $data ) {
		global $wpdb;
		$slug = sanitize_key( $data['slug'] ?? '' );
		if ( '' === $slug ) {
			return new WP_Error( 'g2a_crm_validation', __( 'Slug is required.', 'guns2ammo-crm' ), array( 'status' => 400 ) );
		}

		$existing = self::find_by_slug( $slug );
		$now      = g2a_crm_now();

		$row = array(
			'slug'       => $slug,
			'channel'    => g2a_crm_allowed_status( $data['channel'] ?? 'email', self::CHANNELS, 'email' ),
			'subject'    => sanitize_text_field( (string) ( $data['subject'] ?? '' ) ),
			'body'       => isset( $data['body'] ) ? wp_kses_post( (string) $data['body'] ) : '',
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'updated_at' => $now,
		);

		if ( $existing ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $existing['id'] ) );
			$new = self::find( $existing['id'] );
			$diff = G2A_CRM_Audit_Log::diff( $existing, $new );
			if ( ! empty( $diff['new'] ) ) {
				G2A_CRM_Audit_Log::record( 'template.update', 'template', (int) $existing['id'], $diff['old'], $diff['new'] );
			}
			return $new;
		}

		$row['created_by'] = get_current_user_id() ?: null;
		$row['created_at'] = $now;
		$result = $wpdb->insert( self::table(), $row );
		if ( false === $result ) {
			return new WP_Error( 'g2a_crm_db_insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}
		$new = self::find( (int) $wpdb->insert_id );
		G2A_CRM_Audit_Log::record( 'template.create', 'template', (int) $new['id'], null, $new );
		return $new;
	}

	public static function delete( $id ) {
		$existing = self::find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'g2a_crm_not_found', __( 'Template not found.', 'guns2ammo-crm' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		G2A_CRM_Audit_Log::record( 'template.delete', 'template', (int) $id, $existing, null );
		return true;
	}

	/**
	 * Install seed templates on first run. Idempotent — only inserts missing slugs.
	 */
	public static function install_defaults() {
		$defaults = self::default_templates();
		foreach ( $defaults as $tpl ) {
			if ( ! self::find_by_slug( $tpl['slug'] ) ) {
				self::upsert( array_merge( $tpl, array( 'is_active' => 1 ) ) );
			}
		}
	}

	public static function default_templates() {
		return array(
			array(
				'slug'    => 'booking_reminder',
				'channel' => 'email',
				'subject' => 'Reminder: your {{booking_type}} booking on {{booking_date}}',
				'body'    => "Hi {{first_name}},\n\nThis is a reminder that your {{booking_type}} booking is scheduled for {{booking_time}} on {{booking_date}}.\n\nPlease arrive 10 minutes early. If you don't have a waiver on file, you can complete one at the kiosk when you arrive.\n\n— The {{business_name}} team",
			),
			array(
				'slug'    => 'booking_reminder_sms',
				'channel' => 'sms',
				'subject' => '',
				'body'    => '{{business_name}}: your booking is at {{booking_time}} on {{booking_date}}. Complete waiver: {{waiver_link}}',
			),
			array(
				'slug'    => 'waiver_expiry',
				'channel' => 'email',
				'subject' => 'Your range waiver is expiring soon',
				'body'    => "Hi {{first_name}},\n\nYour signed range waiver expires on {{waiver_expires}}. Stop by the kiosk on your next visit to re-sign — it takes about a minute.\n\n— {{business_name}}",
			),
			array(
				'slug'    => 'membership_renewal',
				'channel' => 'email',
				'subject' => 'Your {{plan_name}} membership renews soon',
				'body'    => "Hi {{first_name}},\n\nYour '{{plan_name}}' membership renews on {{renewal_date}}. If you'd like to change plans or update payment, reply or stop by.\n\n— {{business_name}}",
			),
			array(
				'slug'    => 'class_reminder',
				'channel' => 'email',
				'subject' => 'Reminder: {{class_name}} tomorrow',
				'body'    => "Hi {{first_name}},\n\nYour class '{{class_name}}' starts at {{class_time}}. Please arrive 15 minutes early to sign in.\n\nRequired waiver and gear info is in your confirmation email — bring questions to the instructor on arrival.\n\n— {{business_name}}",
			),
			array(
				'slug'    => 'class_followup',
				'channel' => 'email',
				'subject' => 'Thanks for taking {{class_name}}',
				'body'    => "Hi {{first_name}},\n\nThanks for joining us for '{{class_name}}'. We'd love your feedback — just reply or stop by on your next visit.\n\nInterested in next steps or a membership? Just ask any staff member.\n\n— {{business_name}}",
			),
		);
	}
}
