<?php
/**
 * Roles and capabilities for the CRM.
 *
 * Capability convention: g2a_crm_<verb>_<noun>. Sensitive verbs are deliberately
 * scoped narrowly so we can grant view-but-not-export, etc.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Permissions {

	const CAPS = array(
		// Customers.
		'g2a_crm_view_customers',
		'g2a_crm_edit_customers',
		'g2a_crm_delete_customers',

		// Leads.
		'g2a_crm_view_leads',
		'g2a_crm_edit_leads',
		'g2a_crm_delete_leads',

		// Bookings.
		'g2a_crm_view_bookings',
		'g2a_crm_edit_bookings',
		'g2a_crm_cancel_bookings',

		// Memberships.
		'g2a_crm_view_memberships',
		'g2a_crm_edit_memberships',

		// Classes.
		'g2a_crm_view_classes',
		'g2a_crm_edit_classes',
		'g2a_crm_manage_class_roster',

		// Waivers.
		'g2a_crm_view_waivers',
		'g2a_crm_verify_waivers',

		// Messages.
		'g2a_crm_send_email',
		'g2a_crm_send_sms',
		'g2a_crm_manage_templates',

		// Tasks.
		'g2a_crm_view_tasks',
		'g2a_crm_edit_tasks',

		// Tags.
		'g2a_crm_manage_tags',

		// Reports.
		'g2a_crm_view_reports',
		'g2a_crm_export_data',

		// Sensitive / compliance.
		'g2a_crm_view_sensitive_records',
		'g2a_crm_edit_sensitive_records',

		// Settings & audit.
		'g2a_crm_manage_settings',
		'g2a_crm_view_audit_logs',

		// Integrations.
		'g2a_crm_manage_webhooks',
		'g2a_crm_manage_integrations',
	);

	const ROLES = array(
		'crm_owner'              => 'CRM Owner',
		'crm_manager'            => 'CRM Manager',
		'crm_compliance_officer' => 'CRM Compliance Officer',
		'crm_range_staff'        => 'Range Staff',
		'crm_instructor'         => 'Instructor',
		'crm_sales'              => 'Sales / Front Desk',
		'crm_marketing'          => 'CRM Marketing',
		'crm_accountant'         => 'CRM Accountant',
	);

	public static function ensure_roles_exist() {
		// Administrator gets everything.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		self::ensure_role(
			'crm_owner',
			'CRM Owner',
			array_fill_keys( self::CAPS, true )
		);

		self::ensure_role(
			'crm_manager',
			'CRM Manager',
			array(
				'read'                          => true,
				'g2a_crm_view_customers'        => true,
				'g2a_crm_edit_customers'        => true,
				'g2a_crm_view_leads'            => true,
				'g2a_crm_edit_leads'            => true,
				'g2a_crm_view_bookings'         => true,
				'g2a_crm_edit_bookings'         => true,
				'g2a_crm_cancel_bookings'       => true,
				'g2a_crm_view_memberships'      => true,
				'g2a_crm_edit_memberships'      => true,
				'g2a_crm_view_classes'          => true,
				'g2a_crm_edit_classes'          => true,
				'g2a_crm_manage_class_roster'   => true,
				'g2a_crm_view_waivers'          => true,
				'g2a_crm_verify_waivers'        => true,
				'g2a_crm_send_email'            => true,
				'g2a_crm_send_sms'              => true,
				'g2a_crm_manage_templates'      => true,
				'g2a_crm_view_tasks'            => true,
				'g2a_crm_edit_tasks'            => true,
				'g2a_crm_manage_tags'           => true,
				'g2a_crm_view_reports'          => true,
				'g2a_crm_export_data'           => true,
				'g2a_crm_view_audit_logs'       => true,
				'g2a_crm_manage_webhooks'       => true,
				'g2a_crm_manage_integrations'   => true,
			)
		);

		self::ensure_role(
			'crm_compliance_officer',
			'CRM Compliance Officer',
			array(
				'read'                            => true,
				'g2a_crm_view_customers'          => true,
				'g2a_crm_view_waivers'            => true,
				'g2a_crm_verify_waivers'          => true,
				'g2a_crm_view_sensitive_records'  => true,
				'g2a_crm_edit_sensitive_records'  => true,
				'g2a_crm_view_audit_logs'         => true,
				'g2a_crm_view_tasks'              => true,
				'g2a_crm_edit_tasks'              => true,
			)
		);

		self::ensure_role(
			'crm_range_staff',
			'Range Staff',
			array(
				'read'                          => true,
				'g2a_crm_view_customers'        => true,
				'g2a_crm_view_bookings'         => true,
				'g2a_crm_edit_bookings'         => true,
				'g2a_crm_view_waivers'          => true,
				'g2a_crm_view_memberships'      => true,
				'g2a_crm_view_classes'          => true,
				'g2a_crm_view_tasks'            => true,
				'g2a_crm_edit_tasks'            => true,
			)
		);

		self::ensure_role(
			'crm_instructor',
			'Instructor',
			array(
				'read'                          => true,
				'g2a_crm_view_customers'        => true,
				'g2a_crm_view_bookings'         => true,
				'g2a_crm_view_classes'          => true,
				'g2a_crm_manage_class_roster'   => true,
				'g2a_crm_view_tasks'            => true,
				'g2a_crm_edit_tasks'            => true,
			)
		);

		self::ensure_role(
			'crm_sales',
			'Sales / Front Desk',
			array(
				'read'                          => true,
				'g2a_crm_view_customers'        => true,
				'g2a_crm_edit_customers'        => true,
				'g2a_crm_view_leads'            => true,
				'g2a_crm_edit_leads'            => true,
				'g2a_crm_view_bookings'         => true,
				'g2a_crm_edit_bookings'         => true,
				'g2a_crm_view_memberships'      => true,
				'g2a_crm_edit_memberships'      => true,
				'g2a_crm_view_classes'          => true,
				'g2a_crm_manage_class_roster'   => true,
				'g2a_crm_view_tasks'            => true,
				'g2a_crm_edit_tasks'            => true,
				'g2a_crm_manage_tags'           => true,
				'g2a_crm_send_email'            => true,
				'g2a_crm_send_sms'              => true,
			)
		);

		self::ensure_role(
			'crm_marketing',
			'CRM Marketing',
			array(
				'read'                     => true,
				'g2a_crm_view_customers'   => true,
				'g2a_crm_view_leads'       => true,
				'g2a_crm_send_email'       => true,
				'g2a_crm_send_sms'         => true,
				'g2a_crm_manage_templates' => true,
				'g2a_crm_manage_tags'      => true,
				'g2a_crm_view_reports'     => true,
			)
		);

		self::ensure_role(
			'crm_accountant',
			'CRM Accountant',
			array(
				'read'                     => true,
				'g2a_crm_view_reports'         => true,
				'g2a_crm_export_data'          => true,
				'g2a_crm_view_memberships'     => true,
				'g2a_crm_view_classes'         => true,
				'g2a_crm_manage_integrations'  => true,
			)
		);
	}

	private static function ensure_role( $slug, $label, $caps ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $label, $caps );
			return;
		}
		foreach ( $caps as $cap => $granted ) {
			if ( $granted ) {
				$role->add_cap( $cap );
			} else {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Standard permission check used by REST controllers.
	 */
	public static function user_can( $cap ) {
		return current_user_can( $cap );
	}
}
