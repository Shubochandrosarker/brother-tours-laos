<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Frontend;

/**
 * Front-end assets for the booking forms: a small vanilla-JS handler + the REST
 * endpoint and nonce, plus minimal styling that inherits the theme tokens.
 */
final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		wp_register_script( 'wpistic-tm-booking', WPISTIC_TM_URL . 'assets/js/booking.js', array(), WPISTIC_TM_VERSION, true );
		wp_localize_script(
			'wpistic-tm-booking',
			'wpisticTM',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'wpistic/v1/booking' ) ),
				'newsletterUrl' => esc_url_raw( rest_url( 'wpistic/v1/newsletter' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_enqueue_script( 'wpistic-tm-booking' );
		wp_enqueue_style( 'wpistic-tm-booking', WPISTIC_TM_URL . 'assets/css/booking.css', array(), WPISTIC_TM_VERSION );
	}
}
