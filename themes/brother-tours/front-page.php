<?php
/**
 * Reversible visual homepage bridge.
 *
 * Content Studio takes over only when its setting is enabled and the assigned
 * homepage contains actual block content. Otherwise the parent template keeps
 * rendering the existing production-safe design.
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\\BrotherTours\\ContentStudio\\Templates' ) && \BrotherTours\ContentStudio\Templates::render_front_page() ) {
	return;
}

require get_template_directory() . '/front-page.php';
