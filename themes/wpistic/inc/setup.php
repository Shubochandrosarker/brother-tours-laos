<?php
/**
 * Theme setup: supports and menu locations.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'wpistic_setup' );

/**
 * Register theme supports and navigation menus.
 *
 * @return void
 */
function wpistic_setup() {
	load_theme_textdomain( 'wpistic', WPISTIC_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );

	// Editor inherits the design tokens so Gutenberg/Elementor previews match the front end.
	add_editor_style( array( 'assets/css/tokens.css', 'assets/css/components.css' ) );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 48,
			'width'       => 200,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'primary'             => __( 'Primary Menu', 'wpistic' ),
			'footer-journeys'     => __( 'Footer — Journeys', 'wpistic' ),
			'footer-destinations' => __( 'Footer — Destinations', 'wpistic' ),
			'footer-company'      => __( 'Footer — The Company', 'wpistic' ),
			'footer-practical'    => __( 'Footer — Practical', 'wpistic' ),
		)
	);
}
