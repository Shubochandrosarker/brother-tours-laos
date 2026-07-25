<?php
/**
 * Elementor support: theme locations, CPT editing support, and a widget
 * category for Brother Tours blocks and embeds. All hooks only fire when
 * Elementor is active.
 *
 * Theme *locations* are registered here (elementor/theme/register_locations);
 * templates then consult elementor_theme_do_location() to decide whether to
 * render their own PHP markup or let an assigned Elementor template take
 * over -- see header.php, footer.php, single*.php, archive*.php and 404.php.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'elementor/theme/register_locations', 'wpistic_elementor_locations' );

/**
 * Register Elementor's core theme locations (header, footer, single, archive, 404).
 *
 * @param object $manager Elementor locations manager.
 * @return void
 */
function wpistic_elementor_locations( $manager ) {
	if ( method_exists( $manager, 'register_all_core_location' ) ) {
		$manager->register_all_core_location();
	}
}

add_action( 'elementor/elements/categories_registered', 'wpistic_elementor_category' );

/**
 * Register the Brother Tours widget category.
 *
 * @param object $elements_manager Elementor elements manager.
 * @return void
 */
function wpistic_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'brother-tours',
		array(
			'title' => __( 'Brother Tours', 'wpistic' ),
			// A travel/location glyph reads better here than the generic
			// "plug" icon Elementor suggests for arbitrary integrations.
			'icon'  => 'eicon-map-pin',
		)
	);
}

add_filter( 'elementor_cpt_support', 'wpistic_elementor_cpt_support' );

/**
 * Extend Elementor editing support to the Tour Manager content types.
 *
 * Additive only: Elementor's own default already covers `page`/`post`, and a
 * site operator may already have opted other post types in from
 * Elementor > Settings. Returning array_merge() (never a bare overwrite)
 * means this can never silently drop a post type someone else enabled.
 *
 * @param array $post_types Post type slugs currently Elementor-editable.
 * @return array
 */
function wpistic_elementor_cpt_support( $post_types ) {
	$post_types = is_array( $post_types ) ? $post_types : array();

	return array_values(
		array_unique(
			array_merge(
				$post_types,
				array( 'wpistic_tour', 'wpistic_destination', 'wpistic_experience' )
			)
		)
	);
}
