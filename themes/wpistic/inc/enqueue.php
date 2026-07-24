<?php
/**
 * Front-end assets: tokens, base styles, components, templates, fonts, navigation script.
 *
 * Performance discipline: tokens load first, no jQuery dependency, front-end script
 * deferred, fonts load with display=swap behind preconnect. A tiny no-flash theme
 * snippet is printed inline in header.php (before paint) to set the light/dark mode.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'wpistic_enqueue_assets' );

/**
 * Enqueue theme styles and scripts.
 *
 * Cascade: tokens → base (style.css) → components → templates. All share the
 * design tokens so classic templates, Gutenberg, and Elementor stay consistent.
 *
 * @return void
 */
function wpistic_enqueue_assets() {
	wp_enqueue_style( 'wpistic-fonts', wpistic_fonts_url(), array(), null );
	wp_enqueue_style( 'wpistic-tokens', WPISTIC_URI . '/assets/css/tokens.css', array(), WPISTIC_VERSION );
	wp_enqueue_style( 'wpistic-style', WPISTIC_URI . '/style.css', array( 'wpistic-tokens' ), WPISTIC_VERSION );
	wp_enqueue_style( 'wpistic-components', WPISTIC_URI . '/assets/css/components.css', array( 'wpistic-style' ), WPISTIC_VERSION );
	wp_enqueue_style( 'wpistic-pages', WPISTIC_URI . '/assets/css/pages.css', array( 'wpistic-components' ), WPISTIC_VERSION );

	wp_enqueue_script( 'wpistic-navigation', WPISTIC_URI . '/assets/js/navigation.js', array(), WPISTIC_VERSION, true );
}

/**
 * Google Fonts URL for the brand families (Cormorant Garamond + Manrope + Caveat).
 *
 * @return string
 */
function wpistic_fonts_url() {
	return 'https://fonts.googleapis.com/css2'
		. '?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600'
		. '&family=Manrope:wght@300;400;500;600;700'
		. '&family=Caveat:wght@500;600'
		. '&display=swap';
}

add_filter( 'wp_resource_hints', 'wpistic_resource_hints', 10, 2 );

/**
 * Preconnect to the font host to cut latency.
 *
 * @param array  $hints    Resource hints.
 * @param string $relation Relation type.
 * @return array
 */
function wpistic_resource_hints( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = array( 'href' => 'https://fonts.googleapis.com' );
		$hints[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}

	return $hints;
}

add_filter( 'script_loader_tag', 'wpistic_defer_scripts', 10, 2 );

/**
 * Defer the theme's own front-end script (no jQuery, no render block).
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function wpistic_defer_scripts( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}

	if ( 'wpistic-navigation' === $handle && false === strpos( $tag, ' defer' ) ) {
		$tag = str_replace( ' src', ' defer src', $tag );
	}

	return $tag;
}
