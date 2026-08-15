<?php
/**
 * Theme Options — the complete "Brother Tours" configuration panel.
 *
 * One clean, native, no-lock-in surface (live-previewing) where the client controls
 * brand assets, colour mode, palette (warmth + accent + navy + green), typography,
 * layout density, the homepage layout, hero imagery, contact, social and footer —
 * all without code. Palette/type changes apply via inc/dynamic-css.php CSS variables;
 * the contrast-bearing gold variants are auto-derived AA-safe, so customization
 * cannot break the accessibility work. Review URLs/embed live in the child theme.
 *
 * @package WPistic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'customize_register', 'wpistic_customize_register' );

/**
 * Register the Theme Options panel, sections, settings, and controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function wpistic_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'wpistic_options',
		array(
			'title'       => __( 'Brother Tours — Theme Options', 'wpistic' ),
			'description' => __( 'Brand, colour mode, palette, typography, layout, homepage and content. All images come from the Media Library; palette/type changes preview live.', 'wpistic' ),
			'priority'    => 5,
		)
	);

	/* ---- control helpers ---- */
	$add_text = static function ( $wp_customize, $section, $id, $label, $default = '', $sanitize = 'sanitize_text_field', $type = 'text', $desc = '' ) {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'description' => $desc, 'section' => $section, 'type' => $type ) );
	};
	$add_media = static function ( $wp_customize, $section, $id, $label, $description = '' ) {
		$wp_customize->add_setting( $id, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $id, array( 'label' => $label, 'description' => $description, 'section' => $section, 'mime_type' => 'image' ) ) );
	};
	$add_color = static function ( $wp_customize, $section, $id, $label, $default, $desc = '' ) {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'refresh' ) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, array( 'label' => $label, 'description' => $desc, 'section' => $section ) ) );
	};
	$add_select = static function ( $wp_customize, $section, $id, $label, $default, $choices, $desc = '' ) {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'wpistic_sanitize_select', 'transport' => 'refresh' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'description' => $desc, 'section' => $section, 'type' => 'select', 'choices' => $choices ) );
	};
	$add_toggle = static function ( $wp_customize, $section, $id, $label, $default, $desc = '' ) {
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'wpistic_sanitize_checkbox' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'description' => $desc, 'section' => $section, 'type' => 'checkbox' ) );
	};

	/* ===== Brand & Layout ===== */
	$wp_customize->add_section( 'wpistic_brand', array( 'title' => __( 'Brand & Identity', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_media( $wp_customize, 'wpistic_brand', 'wpistic_logo', __( 'Logo (optional, transparent PNG/SVG)', 'wpistic' ), __( 'Leave empty to use the text wordmark.', 'wpistic' ) );

	/* ===== Colour Mode ===== */
	$wp_customize->add_section( 'wpistic_mode', array( 'title' => __( 'Colour Mode', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$wp_customize->add_setting( 'wpistic_default_mode', array( 'default' => 'light', 'sanitize_callback' => 'wpistic_sanitize_mode' ) );
	$wp_customize->add_control( 'wpistic_default_mode', array(
		'label'       => __( 'Default mode', 'wpistic' ),
		'description' => __( 'First-time visitors see this; their toggle choice is remembered.', 'wpistic' ),
		'section'     => 'wpistic_mode', 'type' => 'radio',
		'choices'     => array( 'light' => __( 'Light (warm)', 'wpistic' ), 'dark' => __( 'Dark (navy)', 'wpistic' ) ),
	) );
	$add_toggle( $wp_customize, 'wpistic_mode', 'wpistic_enable_toggle', __( 'Show the light/dark toggle', 'wpistic' ), 1, __( 'Uncheck to lock the site to the default mode.', 'wpistic' ) );

	/* ===== Palette ===== */
	$wp_customize->add_section( 'wpistic_palette', array( 'title' => __( 'Palette', 'wpistic' ), 'panel' => 'wpistic_options', 'description' => __( 'Gold text shades and button text are auto-derived against the chosen background to stay WCAG-AA — pick any accent and it remains legible. The navy is auto-darkened where used as a surface.', 'wpistic' ) ) );
	$add_select( $wp_customize, 'wpistic_palette', 'wpistic_warmth', __( 'Background warmth', 'wpistic' ), 'soft', array( 'soft' => __( 'Soft White', 'wpistic' ), 'cream' => __( 'Warm Cream', 'wpistic' ), 'sand' => __( 'Sand', 'wpistic' ) ) );
	$add_color( $wp_customize, 'wpistic_palette', 'wpistic_accent_gold', __( 'Accent (gold)', 'wpistic' ), '#B8893E' );
	$add_color( $wp_customize, 'wpistic_palette', 'wpistic_color_navy', __( 'Dark sections (navy)', 'wpistic' ), '#102A43' );
	$add_color( $wp_customize, 'wpistic_palette', 'wpistic_color_green', __( 'Secondary (green)', 'wpistic' ), '#2F5D50' );

	/* ===== Typography ===== */
	$wp_customize->add_section( 'wpistic_type', array( 'title' => __( 'Typography', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_select( $wp_customize, 'wpistic_type', 'wpistic_body_base', __( 'Body text size', 'wpistic' ), '17', array( '16' => __( 'Small (16px)', 'wpistic' ), '17' => __( 'Default (17px)', 'wpistic' ), '18' => __( 'Large (18px)', 'wpistic' ) ) );
	$add_select( $wp_customize, 'wpistic_type', 'wpistic_display_scale', __( 'Heading scale', 'wpistic' ), '1', array( '0.92' => __( 'Compact', 'wpistic' ), '1' => __( 'Default', 'wpistic' ), '1.08' => __( 'Spacious', 'wpistic' ) ) );

	/* ===== Layout ===== */
	$wp_customize->add_section( 'wpistic_layout', array( 'title' => __( 'Layout', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_select( $wp_customize, 'wpistic_layout', 'wpistic_section_pad', __( 'Section spacing', 'wpistic' ), '100', array( '72' => __( 'Compact', 'wpistic' ), '100' => __( 'Default', 'wpistic' ), '120' => __( 'Roomy', 'wpistic' ) ) );

	/* ===== Homepage ===== */
	$wp_customize->add_section( 'wpistic_home', array( 'title' => __( 'Homepage', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$wp_customize->add_setting( 'wpistic_home_variant', array( 'default' => 'v1', 'sanitize_callback' => 'wpistic_sanitize_variant' ) );
	$wp_customize->add_control( 'wpistic_home_variant', array(
		'label'       => __( 'Homepage layout', 'wpistic' ),
		'description' => __( 'V1 Editorial Cinematic · V2 Asymmetric Story-Led · V3 Magazine Spread. Compare them from the toolbar “Compare layouts” menu, or add ?bt_variant=v2 to the homepage URL (admins).', 'wpistic' ),
		'section'     => 'wpistic_home', 'type' => 'radio',
		'choices'     => array(
			'v1' => __( 'V1 — Editorial Cinematic', 'wpistic' ),
			'v2' => __( 'V2 — Asymmetric Story-Led', 'wpistic' ),
			'v3' => __( 'V3 — Magazine Spread', 'wpistic' ),
		),
	) );

	/* ===== Homepage Hero ===== */
	$wp_customize->add_section( 'wpistic_hero', array( 'title' => __( 'Homepage Hero', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_media( $wp_customize, 'wpistic_hero', 'wpistic_home_hero_image', __( 'Hero background image', 'wpistic' ), __( 'Recommended 2000×1200. Falls back to a bundled photo.', 'wpistic' ) );
	$add_media( $wp_customize, 'wpistic_hero', 'wpistic_hero_host_image', __( 'Host portrait', 'wpistic' ) );
	$add_text( $wp_customize, 'wpistic_hero', 'wpistic_hero_host_name', __( 'Host name', 'wpistic' ), 'Ken FJ Her' );
	$add_text( $wp_customize, 'wpistic_hero', 'wpistic_hero_host_role', __( 'Host role', 'wpistic' ), 'Founder · Licensed Lao National Tour Guide' );
	$add_text( $wp_customize, 'wpistic_hero', 'wpistic_hero_host_quote', __( 'Host one-liner', 'wpistic' ), 'I host the Laos I know — road by road, season by season.', 'sanitize_textarea_field', 'textarea' );

	/* ===== Contact ===== */
	$wp_customize->add_section( 'wpistic_contact', array( 'title' => __( 'Contact Details', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_whatsapp', __( 'WhatsApp number (digits only)', 'wpistic' ), '8562055989894' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_whatsapp_display', __( 'WhatsApp display', 'wpistic' ), '+856 20 55989894' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_email', __( 'Email', 'wpistic' ), 'enquiry@brothertours.com', 'sanitize_email', 'email' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_phone', __( 'Phone', 'wpistic' ), '+856 21 000 000' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_office', __( 'Office line', 'wpistic' ), 'Brother Tours Sole Co., Ltd. · Vientiane, Lao PDR' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_hours', __( 'Office hours', 'wpistic' ), 'Mon–Sat · 8:00–19:00 ICT (UTC+7)' );
	$add_text( $wp_customize, 'wpistic_contact', 'wpistic_contact_locale', __( 'Header locale label', 'wpistic' ), 'EN / USD' );

	/* ===== Social ===== */
	$wp_customize->add_section( 'wpistic_social', array( 'title' => __( 'Social Links', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_text( $wp_customize, 'wpistic_social', 'wpistic_social_instagram', __( 'Instagram URL', 'wpistic' ), '', 'esc_url_raw', 'url' );
	$add_text( $wp_customize, 'wpistic_social', 'wpistic_social_youtube', __( 'YouTube URL', 'wpistic' ), '', 'esc_url_raw', 'url' );

	/* ===== Footer ===== */
	$wp_customize->add_section( 'wpistic_footer', array( 'title' => __( 'Footer', 'wpistic' ), 'panel' => 'wpistic_options' ) );
	$add_text( $wp_customize, 'wpistic_footer', 'wpistic_footer_blurb', __( 'Footer blurb', 'wpistic' ), 'A Lao company. Owned here, run here, guided here.', 'sanitize_textarea_field', 'textarea' );
	$add_text( $wp_customize, 'wpistic_footer', 'wpistic_newsletter_heading', __( 'Newsletter label', 'wpistic' ), 'Slow letters from Ken & the team' );
	$add_text( $wp_customize, 'wpistic_footer', 'wpistic_copyright_extra', __( 'Copyright tagline', 'wpistic' ), 'Lao-led. Globally understood.' );
}

/**
 * Sanitize the colour mode choice.
 *
 * @param string $value Raw value.
 * @return string
 */
function wpistic_sanitize_mode( $value ) {
	return in_array( $value, array( 'light', 'dark' ), true ) ? $value : 'light';
}

/**
 * Sanitize the homepage variant choice.
 *
 * @param string $value Raw value.
 * @return string
 */
function wpistic_sanitize_variant( $value ) {
	return in_array( $value, array( 'v1', 'v2', 'v3' ), true ) ? $value : 'v1';
}

/**
 * Sanitize a select control by matching it against its registered choices.
 *
 * @param string               $value   Raw value.
 * @param WP_Customize_Setting $setting Setting instance.
 * @return string
 */
function wpistic_sanitize_select( $value, $setting ) {
	$control = $setting->manager->get_control( $setting->id );
	$choices = ( $control && ! empty( $control->choices ) ) ? $control->choices : array();
	return array_key_exists( $value, $choices ) ? $value : $setting->default;
}

/**
 * Sanitize a checkbox to 0/1.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function wpistic_sanitize_checkbox( $value ) {
	return ( ! empty( $value ) ) ? 1 : 0;
}
