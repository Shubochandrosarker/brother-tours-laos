<?php
/**
 * Image sizes for the editorial layouts + an Elementor token bridge.
 *
 * Every important image is swappable via the native Media Library (Featured Image
 * on tours/destinations/journal, or media controls in Theme Options). These named
 * sizes keep cards/heroes crisp and within the performance budget (WebP, <250KB).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'wpistic_image_sizes' );

/**
 * Register the theme's named crop sizes.
 *
 * @return void
 */
function wpistic_image_sizes() {
	add_image_size( 'bt-hero', 2000, 1200, true );      // full-bleed mastheads
	add_image_size( 'bt-portrait', 720, 960, true );    // 3:4 founder / host portrait
	add_image_size( 'bt-card', 800, 600, true );        // 4:3 tour / destination card
	add_image_size( 'bt-teaser', 880, 605, true );      // 16:11 journal teaser
	add_image_size( 'bt-gallery', 700, 700, true );     // square gallery cell
}

add_filter( 'image_size_names_choose', 'wpistic_image_size_names' );

/**
 * Expose the named sizes in the media picker so editors can choose them.
 *
 * @param array $sizes Existing size choices.
 * @return array
 */
function wpistic_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'bt-hero'     => __( 'BT — Hero (wide)', 'wpistic' ),
			'bt-portrait' => __( 'BT — Portrait 3:4', 'wpistic' ),
			'bt-card'     => __( 'BT — Card 4:3', 'wpistic' ),
			'bt-teaser'   => __( 'BT — Teaser 16:11', 'wpistic' ),
		)
	);
}

/**
 * Elementor bridge: make sure the design tokens + components load inside the
 * Elementor editor preview so client-built sections match the theme automatically.
 */
add_action(
	'elementor/editor/after_enqueue_styles',
	function () {
		wp_enqueue_style( 'wpistic-tokens', WPISTIC_URI . '/assets/css/tokens.css', array(), WPISTIC_VERSION );
		wp_enqueue_style( 'wpistic-components-ele', WPISTIC_URI . '/assets/css/components.css', array( 'wpistic-tokens' ), WPISTIC_VERSION );
	}
);
