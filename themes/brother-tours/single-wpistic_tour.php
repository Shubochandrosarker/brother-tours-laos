<?php
/** Reversible visual tour template bridge. @package BrotherTours */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\\BrotherTours\\ContentStudio\\Templates' ) && \BrotherTours\ContentStudio\Templates::render_post_content( 'wpistic_tour' ) ) {
	return;
}

require get_template_directory() . '/single-wpistic_tour.php';
