<?php
/**
 * Block patterns — reusable PDF sections the client can drop into any page
 * (block editor) and that read consistently inside Elementor (shared tokens).
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpistic_register_patterns' );

/**
 * Register the WPistic pattern category and starter sections.
 *
 * @return void
 */
function wpistic_register_patterns() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category( 'wpistic', array( 'label' => __( 'Brother Tours', 'wpistic' ) ) );

	$patterns = array(
		'section-heading' => array(
			'title'   => __( 'Section heading (eyebrow + title)', 'wpistic' ),
			'content' => '<!-- wp:paragraph {"className":"eyebrow"} --><p class="eyebrow">Eyebrow</p><!-- /wp:paragraph -->'
				. '<!-- wp:heading {"className":"sec-h2"} --><h2 class="sec-h2">A heading with one <em>word</em> in gold.</h2><!-- /wp:heading -->',
		),
		'navy-cta' => array(
			'title'   => __( 'Navy call-to-action band', 'wpistic' ),
			'content' => '<!-- wp:group {"className":"final","layout":{"type":"constrained"}} --><div class="wp-block-group final">'
				. '<!-- wp:paragraph {"className":"eyebrow center"} --><p class="eyebrow center">The Invitation</p><!-- /wp:paragraph -->'
				. '<!-- wp:heading {"textAlign":"center","className":"final-h2"} --><h2 class="final-h2 has-text-align-center">Tell us what you are <em>imagining</em>.</h2><!-- /wp:heading -->'
				. '<!-- wp:paragraph {"align":"center","className":"final-sub"} --><p class="final-sub has-text-align-center">We design your journey, your way.</p><!-- /wp:paragraph -->'
				. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Build My Trip</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
				. '</div><!-- /wp:group -->',
		),
		'stat-band' => array(
			'title'   => __( 'Stat band (brand-safe facts)', 'wpistic' ),
			'content' => '<!-- wp:group {"className":"stat-band"} --><div class="wp-block-group stat-band">'
				. '<!-- wp:columns --><div class="wp-block-columns">'
				. '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"stat-num"} --><p class="stat-num">2010</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"stat-label"} --><p class="stat-label">Licensed Lao guide</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
				. '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"stat-num"} --><p class="stat-num">2018</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"stat-label"} --><p class="stat-label">Brother Tours founded</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
				. '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"stat-num"} --><p class="stat-num">Vientiane</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"stat-label"} --><p class="stat-label">Lao-owned, Lao-based</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
				. '</div><!-- /wp:columns --></div><!-- /wp:group -->',
		),
	);

	foreach ( $patterns as $slug => $pattern ) {
		register_block_pattern(
			'wpistic/' . $slug,
			array(
				'title'      => $pattern['title'],
				'categories' => array( 'wpistic' ),
				'content'    => $pattern['content'],
			)
		);
	}
}
