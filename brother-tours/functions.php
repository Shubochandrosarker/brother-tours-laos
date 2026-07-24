<?php
/**
 * Brother Tours child theme bootstrap.
 *
 * Loads after the WPistic parent. Brand-specific PHP (template parts,
 * pattern registration) is added here as Phase 1 continues.
 *
 * @package BrotherTours
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'brother_tours_enqueue', 20 );

/**
 * Load the child stylesheet after the parent's base styles.
 *
 * @return void
 */
function brother_tours_enqueue() {
	wp_enqueue_style(
		'brother-tours-style',
		get_stylesheet_uri(),
		array( 'wpistic-style' ),
		wp_get_theme()->get( 'Version' )
	);
}

add_action( 'send_headers', 'brother_tours_security_headers' );

/**
 * Baseline security headers. HSTS only on HTTPS. CSP is intentionally left to the host
 * /.htaccess to avoid breaking embeds; add it there once asset sources are known.
 *
 * @return void
 */
function brother_tours_security_headers() {
	if ( is_admin() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
}

add_action( 'customize_register', 'brother_tours_customize' );

/**
 * Customizer: live review profile links + optional widget embed.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @return void
 */
function brother_tours_customize( $wp_customize ) {
	$wp_customize->add_section( 'brother_tours_reviews', array( 'title' => __( 'Brother Tours — Reviews', 'brother-tours' ), 'priority' => 160 ) );

	$wp_customize->add_setting( 'wpistic_google_reviews_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'wpistic_google_reviews_url', array( 'label' => __( 'Google reviews URL', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'url' ) );

	$wp_customize->add_setting( 'wpistic_tripadvisor_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'wpistic_tripadvisor_url', array( 'label' => __( 'TripAdvisor URL', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'url' ) );

	$wp_customize->add_setting( 'wpistic_reviews_embed', array( 'sanitize_callback' => 'wp_kses_post' ) );
	$wp_customize->add_control( 'wpistic_reviews_embed', array( 'label' => __( 'Reviews widget embed (HTML)', 'brother-tours' ), 'section' => 'brother_tours_reviews', 'type' => 'textarea' ) );
}
