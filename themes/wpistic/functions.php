<?php

/**
 * WPistic parent theme bootstrap.
 *
 * Presentation only. All tour/booking/payment logic lives in the
 * wpistic-tour-manager plugin; all SEO lives in the SEOISTIC plugin.
 *
 * @package WPistic
 */

if (! defined('ABSPATH')) {
	exit;
}

define('WPISTIC_VERSION', '2.5.0');
define('WPISTIC_DIR', get_template_directory());
define('WPISTIC_URI', get_template_directory_uri());

/**
 * Load theme includes (guarded so a partial deploy never fatals).
 */
foreach (array('setup', 'images', 'enqueue', 'template-tags', 'sample-data', 'customizer', 'dynamic-css', 'patterns', 'elementor') as $wpistic_inc) {
	$wpistic_file = WPISTIC_DIR . '/inc/' . $wpistic_inc . '.php';
	if (is_readable($wpistic_file)) {
		require_once $wpistic_file;
	}
}
