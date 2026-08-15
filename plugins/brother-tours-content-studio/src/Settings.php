<?php

declare(strict_types=1);

namespace BrotherTours\ContentStudio;

final class Settings {
	public const OPTION = 'bt_cs_settings';

	public static function activate(): void {
		$defaults = array(
			'visual_homepage'       => false,
			'organization_name'     => 'Brother Tours',
			'organization_url'      => home_url( '/' ),
			'organization_logo_id'  => 0,
			'founder_name'          => '',
			'founder_credentials'   => '',
			'contact_email'         => '',
			'contact_phone'         => '',
			'whatsapp_url'          => '',
			'footer_copyright'      => '',
		);

		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $defaults, '', false );
		}
	}

	public static function get( string $key, mixed $default = '' ): mixed {
		$settings = get_option( self::OPTION, array() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Content Studio', 'brother-tours-content-studio' ),
			__( 'Content Studio', 'brother-tours-content-studio' ),
			Capabilities::MANAGE_CONTENT,
			'bt-content-studio',
			array( $this, 'page' ),
			'dashicons-layout',
			26
		);
	}

	public function settings(): void {
		register_setting( 'bt_cs_settings', self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
		add_settings_section( 'bt_cs_brand', __( 'Global brand controls', 'brother-tours-content-studio' ), '__return_false', 'bt-content-studio' );

		$fields = array(
			'visual_homepage'      => array( 'Visual homepage', 'checkbox' ),
			'organization_name'    => array( 'Organization name', 'text' ),
			'organization_url'     => array( 'Organization URL', 'url' ),
			'organization_logo_id' => array( 'Logo attachment ID', 'number' ),
			'founder_name'         => array( 'Founder name', 'text' ),
			'founder_credentials'  => array( 'Founder credentials', 'text' ),
			'contact_email'        => array( 'Contact email', 'email' ),
			'contact_phone'        => array( 'Contact phone', 'text' ),
			'whatsapp_url'         => array( 'WhatsApp URL', 'url' ),
			'footer_copyright'     => array( 'Footer copyright', 'text' ),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field( 'bt_cs_' . $key, $field[0], array( $this, 'field' ), 'bt-content-studio', 'bt_cs_brand', array( 'key' => $key, 'type' => $field[1] ) );
		}
	}

	/** @param array<string,mixed> $args */
	public function field( array $args ): void {
		$key   = (string) $args['key'];
		$type  = (string) $args['type'];
		$value = self::get( $key );
		$name  = esc_attr( self::OPTION . '[' . $key . ']' );
		if ( 'checkbox' === $type ) {
			printf( '<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>', $name, checked( $value, true, false ), esc_html__( 'Use the visual homepage when the front page has no block content.', 'brother-tours-content-studio' ) );
			return;
		}
		printf( '<input class="regular-text" type="%1$s" name="%2$s" value="%3$s">', esc_attr( $type ), $name, esc_attr( (string) $value ) );
	}

	public function page(): void {
		if ( ! current_user_can( Capabilities::MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Content Studio settings.', 'brother-tours-content-studio' ) );
		}
		?><div class="wrap">
			<h1><?php esc_html_e( 'Brother Tours Content Studio', 'brother-tours-content-studio' ); ?></h1>
			<p><?php esc_html_e( 'Global settings are intentionally limited to content and brand controls. Typography, spacing and responsive design remain protected by the theme.', 'brother-tours-content-studio' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'bt_cs_settings' ); do_settings_sections( 'bt-content-studio' ); submit_button(); ?>
			</form>
		</div><?php
	}

	/** @param mixed $value @return array<string,mixed> */
	public function sanitize( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();
		return array(
			'visual_homepage'      => ! empty( $value['visual_homepage'] ),
			'organization_name'    => sanitize_text_field( (string) ( $value['organization_name'] ?? '' ) ),
			'organization_url'     => esc_url_raw( (string) ( $value['organization_url'] ?? '' ) ),
			'organization_logo_id' => absint( $value['organization_logo_id'] ?? 0 ),
			'founder_name'         => sanitize_text_field( (string) ( $value['founder_name'] ?? '' ) ),
			'founder_credentials'  => sanitize_text_field( (string) ( $value['founder_credentials'] ?? '' ) ),
			'contact_email'        => sanitize_email( (string) ( $value['contact_email'] ?? '' ) ),
			'contact_phone'        => sanitize_text_field( (string) ( $value['contact_phone'] ?? '' ) ),
			'whatsapp_url'         => esc_url_raw( (string) ( $value['whatsapp_url'] ?? '' ) ),
			'footer_copyright'     => sanitize_text_field( (string) ( $value['footer_copyright'] ?? '' ) ),
		);
	}
}
