<?php
/**
 * Brother Tours — Elementor widget bootstrap.
 *
 * Registers the shared widget stylesheet (registered, not enqueued — it only
 * loads on a page that actually places one of these widgets, via each
 * widget's get_style_depends()) and the 18 Brother Tours widgets themselves.
 *
 * Everything here only runs when Elementor is active: widget registration is
 * entirely inside the `elementor/widgets/register` callback, which Elementor
 * itself fires, so none of this can fatal or warn on a site without
 * Elementor installed.
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'brother_tours_register_elementor_widget_style' );

/**
 * Register (do not enqueue) the shared widget stylesheet.
 *
 * @return void
 */
function brother_tours_register_elementor_widget_style() {
	wp_register_style(
		'brother-tours-elementor-widgets',
		get_stylesheet_directory_uri() . '/assets/css/elementor-widgets.css',
		array( 'brother-tours-tokens' ),
		wp_get_theme()->get( 'Version' )
	);
}

add_action( 'elementor/widgets/register', 'brother_tours_register_elementor_widgets' );

/**
 * Register the 18 Brother Tours Elementor widgets.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 * @return void
 */
function brother_tours_register_elementor_widgets( $widgets_manager ) {
	$dir = get_stylesheet_directory() . '/inc/elementor/';

	foreach (
		array(
			'class-tour-widgets.php',
			'class-destination-widgets.php',
			'class-form-widgets.php',
			'class-misc-widgets.php',
		) as $file
	) {
		$path = $dir . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}

	foreach (
		array(
			'Brother_Tours_Widget_Tour_Hero',
			'Brother_Tours_Widget_Tour_Grid',
			'Brother_Tours_Widget_Tour_Search_Filters',
			'Brother_Tours_Widget_Tour_Facts',
			'Brother_Tours_Widget_Tour_Pricing',
			'Brother_Tours_Widget_Tour_Itinerary',
			'Brother_Tours_Widget_Included_Excluded',
			'Brother_Tours_Widget_Tour_Gallery',
			'Brother_Tours_Widget_Tour_Faq',
			'Brother_Tours_Widget_Related_Tours',
			'Brother_Tours_Widget_Destination_Hero',
			'Brother_Tours_Widget_Destination_Experiences',
			'Brother_Tours_Widget_Request_Availability',
			'Brother_Tours_Widget_Formistic_Form',
			'Brother_Tours_Widget_Build_My_Trip_Cta',
			'Brother_Tours_Widget_Reviews',
			'Brother_Tours_Widget_Newsletter_Form',
			'Brother_Tours_Widget_Contact_Information',
		) as $class
	) {
		if ( class_exists( $class ) ) {
			$widgets_manager->register( new $class() );
		}
	}
}
