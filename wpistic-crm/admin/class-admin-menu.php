<?php
/**
 * WP admin menu integration + React dashboard host page.
 *
 * @package G2A_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class G2A_CRM_Admin_Menu {

	private static $instance = null;
	private $hook_suffix     = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'WPistic CRM', 'guns2ammo-crm' ),
			__( 'WPistic CRM', 'guns2ammo-crm' ),
			'g2a_crm_view_customers',
			'g2a-crm',
			array( $this, 'render_dashboard' ),
			'dashicons-businessman',
			26
		);

		add_submenu_page( 'g2a-crm', __( 'Dashboard', 'guns2ammo-crm' ), __( 'Dashboard', 'guns2ammo-crm' ), 'g2a_crm_view_customers', 'g2a-crm', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Calendar', 'guns2ammo-crm' ), __( 'Calendar', 'guns2ammo-crm' ), 'g2a_crm_view_bookings', 'g2a-crm#/calendar', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Bookings', 'guns2ammo-crm' ), __( 'Bookings', 'guns2ammo-crm' ), 'g2a_crm_view_bookings', 'g2a-crm#/bookings', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Travelers', 'guns2ammo-crm' ), __( 'Travelers', 'guns2ammo-crm' ), 'g2a_crm_view_customers', 'g2a-crm#/customers', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Leads', 'guns2ammo-crm' ), __( 'Leads', 'guns2ammo-crm' ), 'g2a_crm_view_leads', 'g2a-crm#/leads', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Tasks', 'guns2ammo-crm' ), __( 'Tasks', 'guns2ammo-crm' ), 'g2a_crm_view_tasks', 'g2a-crm#/tasks', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Messages', 'guns2ammo-crm' ), __( 'Messages', 'guns2ammo-crm' ), 'g2a_crm_view_customers', 'g2a-crm#/messages', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Templates', 'guns2ammo-crm' ), __( 'Templates', 'guns2ammo-crm' ), 'g2a_crm_send_email', 'g2a-crm#/templates', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Reports', 'guns2ammo-crm' ), __( 'Reports', 'guns2ammo-crm' ), 'g2a_crm_view_reports', 'g2a-crm#/reports', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Exports', 'guns2ammo-crm' ), __( 'Exports', 'guns2ammo-crm' ), 'g2a_crm_export_data', 'g2a-crm#/exports', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Webhooks', 'guns2ammo-crm' ), __( 'Webhooks', 'guns2ammo-crm' ), 'g2a_crm_manage_webhooks', 'g2a-crm#/webhooks', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Integrations', 'guns2ammo-crm' ), __( 'Integrations', 'guns2ammo-crm' ), 'g2a_crm_manage_integrations', 'g2a-crm#/integrations', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Suppressions', 'guns2ammo-crm' ), __( 'Suppressions', 'guns2ammo-crm' ), 'g2a_crm_manage_settings', 'g2a-crm#/suppressions', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Audit Logs', 'guns2ammo-crm' ), __( 'Audit Logs', 'guns2ammo-crm' ), 'g2a_crm_view_audit_logs', 'g2a-crm#/audit-logs', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'g2a-crm', __( 'Settings', 'guns2ammo-crm' ), __( 'Settings', 'guns2ammo-crm' ), 'g2a_crm_manage_settings', 'g2a-crm#/settings', array( $this, 'render_dashboard' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$build_dir  = G2A_CRM_PLUGIN_DIR . 'admin/app/dist/';
		$build_url  = G2A_CRM_PLUGIN_URL . 'admin/app/dist/';
		$manifest_p = $build_dir . '.vite/manifest.json';

		if ( file_exists( $manifest_p ) ) {
			$manifest = json_decode( (string) file_get_contents( $manifest_p ), true );
			if ( is_array( $manifest ) && isset( $manifest['src/main.jsx'] ) ) {
				$entry = $manifest['src/main.jsx'];

				wp_enqueue_script(
					'g2a-crm-app',
					$build_url . $entry['file'],
					array( 'wp-element' ),
					G2A_CRM_VERSION,
					true
				);

				if ( ! empty( $entry['css'] ) ) {
					foreach ( $entry['css'] as $i => $css_file ) {
						wp_enqueue_style(
							'g2a-crm-app-' . $i,
							$build_url . $css_file,
							array(),
							G2A_CRM_VERSION
						);
					}
				}
			}
		} else {
			// Dev fallback: a minimal style so the "build needed" message is visible.
			wp_register_style( 'g2a-crm-app-fallback', false, array(), G2A_CRM_VERSION );
			wp_enqueue_style( 'g2a-crm-app-fallback' );
			$fallback_css = '.g2a-crm-bootstrap{padding:40px;background:#080A0D;color:#F5F7FA;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;min-height:80vh}.g2a-crm-bootstrap code{background:#11161C;padding:2px 6px;border-radius:4px;color:#D6A84F}.g2a-crm-bootstrap a{color:#4DA3FF}';
			wp_add_inline_style( 'g2a-crm-app-fallback', $fallback_css );
		}

		wp_localize_script(
			'g2a-crm-app',
			'G2A_CRM_BOOT',
			array(
				'restRoot'   => esc_url_raw( rest_url( G2A_CRM_REST_NAMESPACE ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'  => esc_url_raw( G2A_CRM_PLUGIN_URL ),
				'adminUrl'   => esc_url_raw( admin_url( 'admin.php?page=g2a-crm' ) ),
				'capabilities' => array(
					'view_customers'        => current_user_can( 'g2a_crm_view_customers' ),
					'edit_customers'        => current_user_can( 'g2a_crm_edit_customers' ),
					'delete_customers'      => current_user_can( 'g2a_crm_delete_customers' ),
					'view_leads'            => current_user_can( 'g2a_crm_view_leads' ),
					'edit_leads'            => current_user_can( 'g2a_crm_edit_leads' ),
					'view_tasks'            => current_user_can( 'g2a_crm_view_tasks' ),
					'edit_tasks'            => current_user_can( 'g2a_crm_edit_tasks' ),
					'view_reports'          => current_user_can( 'g2a_crm_view_reports' ),
					'export_data'           => current_user_can( 'g2a_crm_export_data' ),
					'view_audit_logs'       => current_user_can( 'g2a_crm_view_audit_logs' ),
					'view_sensitive'        => current_user_can( 'g2a_crm_view_sensitive_records' ),
					'edit_sensitive'        => current_user_can( 'g2a_crm_edit_sensitive_records' ),
					'view_bookings'         => current_user_can( 'g2a_crm_view_bookings' ),
					'edit_bookings'         => current_user_can( 'g2a_crm_edit_bookings' ),
					'cancel_bookings'       => current_user_can( 'g2a_crm_cancel_bookings' ),
					'view_waivers'          => current_user_can( 'g2a_crm_view_waivers' ),
					'verify_waivers'        => current_user_can( 'g2a_crm_verify_waivers' ),
					'view_memberships'      => current_user_can( 'g2a_crm_view_memberships' ),
					'edit_memberships'      => current_user_can( 'g2a_crm_edit_memberships' ),
					'view_classes'          => current_user_can( 'g2a_crm_view_classes' ),
					'edit_classes'          => current_user_can( 'g2a_crm_edit_classes' ),
					'manage_class_roster'   => current_user_can( 'g2a_crm_manage_class_roster' ),
					'send_email'            => current_user_can( 'g2a_crm_send_email' ),
					'send_sms'              => current_user_can( 'g2a_crm_send_sms' ),
					'manage_templates'      => current_user_can( 'g2a_crm_manage_templates' ),
					'manage_tags'           => current_user_can( 'g2a_crm_manage_tags' ),
					'manage_settings'       => current_user_can( 'g2a_crm_manage_settings' ),
					'manage_webhooks'       => current_user_can( 'g2a_crm_manage_webhooks' ),
					'manage_integrations'   => current_user_can( 'g2a_crm_manage_integrations' ),
				),
				'currentUser' => array(
					'id'   => get_current_user_id(),
					'name' => wp_get_current_user()->display_name,
				),
			)
		);
	}

	public function render_dashboard() {
		if ( ! current_user_can( 'g2a_crm_view_customers' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'guns2ammo-crm' ) );
		}
		include G2A_CRM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
}
