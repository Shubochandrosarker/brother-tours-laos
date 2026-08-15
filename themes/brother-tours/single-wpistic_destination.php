<?php
/** Reversible visual destination template bridge. @package BrotherTours */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\\BrotherTours\\ContentStudio\\Templates' ) && \BrotherTours\ContentStudio\Templates::render_post_content( 'wpistic_destination' ) ) {
	return;
}

require get_template_directory() . '/single-wpistic_destination.php';
