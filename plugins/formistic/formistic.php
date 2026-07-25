<?php

/**
 * Plugin Name:       Formistic
 * Plugin URI:        https://wordpressistic.com/marketplace/plugins/formistic/
 * Description:       Formistic centralizes form capture, lead inbox management, replies, consent capture and delivery logging. This install is the Brother Tours configuration of Formistic; see UPSTREAM.md for the upstream baseline and every local change.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WordPressistic
 * Author URI:        https://wordpressistic.com/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       formistic
 * Domain Path:       /languages
 *
 * @package Wpistic_Formistic
 *
 * Upstream: Formistic by WordPressistic (GPL-2.0+).
 *
 * This fork preserves the public compatibility surface WPistic Tour Manager
 * depends on — Wpistic_Formistic_Database, Wpistic_Formistic_Capture,
 * Wpistic_Formistic_Capture::send_internal() and the
 * `wpistic_formistic_submission_captured` action. Do not rename them without
 * updating every consumer in this repository and shipping an adapter.
 *
 * Customer-facing forms, emails and the acknowledgement flow stay Brother
 * Tours branded (see class-formistic-bt-branding.php); only the plugin's own
 * admin identity is WordPressistic.
 */

if (! defined('ABSPATH')) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Duplicate-plugin guard
 * ---------------------------------------------------------------------------
 * This site must run exactly one Formistic codebase. The upstream standalone
 * plugin declares the same classes, constants and database tables, so loading
 * both would fatal on class redeclaration before WordPress could show a notice.
 *
 * WPISTIC_FORMISTIC_VERSION is defined by both bootstraps, which makes it a
 * reliable "already loaded" sentinel regardless of plugin load order. Whichever
 * bootstrap runs first wins; the second bails out here and explains itself in
 * the admin instead of taking the site down.
 */
if (defined('WPISTIC_FORMISTIC_VERSION')) {

	add_action('admin_notices', function () {
		if (! current_user_can('activate_plugins')) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' .
			esc_html__('Formistic is not running.', 'formistic') .
			'</strong> ' .
			esc_html__('Another copy of Formistic is already active on this site, and only one may load. Deactivate the other Formistic plugin, then reload this page.', 'formistic') .
			'</p></div>';
	});

	// Stop before any include. Loading our classes on top of an already-loaded
	// Formistic would fatal with "Cannot declare class ... already in use".
	return;
}

/**
 * Plugin constants.
 *
 * WPISTIC_FORMISTIC_VERSION    — Plugin version. Bump on every release.
 * WPISTIC_FORMISTIC_DB_VERSION — Schema version. Bump only when DB tables change.
 * WPISTIC_FORMISTIC_FILE       — Absolute path to this bootstrap file.
 * WPISTIC_FORMISTIC_PATH       — Absolute path to plugin directory (trailing slash).
 * WPISTIC_FORMISTIC_URL        — URL to plugin directory (trailing slash).
 * WPISTIC_FORMISTIC_BASENAME   — Plugin basename (folder/file.php) for hooks.
 * BROTHER_TOURS_FORMISTIC      — Marks this install as the Brother Tours
 *                                configuration of Formistic, so Tour Manager
 *                                and the release checks can confirm the
 *                                right build is active.
 */
define('WPISTIC_FORMISTIC_VERSION', '2.0.0');
define('WPISTIC_FORMISTIC_DB_VERSION', '1.3.0');
define('WPISTIC_FORMISTIC_FILE', __FILE__);
define('WPISTIC_FORMISTIC_PATH', plugin_dir_path(__FILE__));
define('WPISTIC_FORMISTIC_URL', plugin_dir_url(__FILE__));
define('WPISTIC_FORMISTIC_BASENAME', plugin_basename(__FILE__));
define('BROTHER_TOURS_FORMISTIC', '2.0.0');

require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-database.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-attachments.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-spam.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-capture.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-shortcode.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-emails.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-autoresponder.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-export.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-bulk.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-gdpr.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-webhooks.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-forms.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-templates.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-analytics.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-settings.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-ai.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-addons.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-api.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-admin.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-newsletter.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-migrate.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-ajax.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-plugin.php';

/* Brother Tours fork additions — admin/email branding and the seeded form set. */
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-bt-branding.php';
require_once WPISTIC_FORMISTIC_PATH . 'includes/class-formistic-bt-forms.php';

/*
 * Activation — create database tables and schedule the daily retention cron,
 * then seed the five Brother Tours forms. Seeding is idempotent and fill-only:
 * a form an operator has already edited is never overwritten.
 */
register_activation_hook(__FILE__, function () {
	Wpistic_Formistic_Database::install();
	Wpistic_Formistic_Newsletter::install();
	Wpistic_Formistic_Gdpr::maybe_schedule();

	/*
	 * Register the form post type before seeding. Activation runs after `init`
	 * has already fired, so the plugin's own `init` callback -- which normally
	 * registers the CPT -- will not run during this request. Seeding against an
	 * unregistered post type is unreliable, so register it explicitly here.
	 */
	(new Wpistic_Formistic_Forms())->register_cpt();
	Wpistic_Formistic_BT_Forms::seed();
});

/* Deactivation — unschedule the cron (options + data persist). */
register_deactivation_hook(__FILE__, ['Wpistic_Formistic_Gdpr', 'unschedule']);

/* Boot on init so WordPress 6.7+ translations are not loaded too early. */
add_action('init', function () {
	Wpistic_Formistic_Plugin::instance()->boot();
	Wpistic_Formistic_BT_Branding::instance()->boot();
}, 5);

/* Safety net if activation seeding never ran. Priority 20 keeps it after the
 * form post type is registered on init. No-ops once seeding has succeeded. */
add_action('init', ['Wpistic_Formistic_BT_Forms', 'maybe_seed'], 20);
