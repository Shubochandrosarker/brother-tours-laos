<?php
/**
 * Elementor support: theme locations + a widget category for Brother Tours
 * blocks and embeds. All hooks only fire when Elementor is active.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'elementor/theme/register_locations', 'wpistic_elementor_locations' );

/**
 * Register Elementor's core theme locations (header, footer, single, archive).
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
			'icon'  => 'fa fa-plug',
		)
	);
}
