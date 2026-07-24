<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Frontend;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Newsletter capture. [wpistic_newsletter] form posts to a REST route that subscribes
 * through Formistic (formistic_add_subscriber). Nonce + honeypot protected.
 */
final class Newsletter {

	public function register(): void {
		add_shortcode( 'wpistic_newsletter', array( $this, 'form' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'wpistic/v1',
			'/newsletter',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return (bool) wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
				},
			)
		);
	}

	public function subscribe( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! empty( $params['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$email = sanitize_email( (string) ( $params['email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'wpistic_invalid_email', __( 'A valid email is required.', 'wpistic-tour-manager' ), array( 'status' => 422 ) );
		}

		$source = 'brother-tours:' . sanitize_text_field( (string) ( $params['source'] ?? 'footer' ) );
		$ok     = false;

		if ( function_exists( 'formistic_add_subscriber' ) ) {
			$ok = (bool) formistic_add_subscriber( $email, $source );
		} else {
			// Fall back to an inquiry so the address is never lost.
			do_action( 'wpistic/notify', array( 'event' => 'inquiry.created', 'booking' => array( 'type' => 'newsletter', 'reference' => 'NL', 'customer_email' => $email ) ) );
			$ok = true;
		}

		return new WP_REST_Response(
			array( 'ok' => $ok, 'message' => __( 'Thank you — you are on the list.', 'wpistic-tour-manager' ) ),
			$ok ? 201 : 200
		);
	}

	public function form( $atts ): string {
		$atts = shortcode_atts( array( 'source' => 'footer' ), $atts, 'wpistic_newsletter' );

		$html  = '<form class="wpistic-booking-form wpistic-newsletter" data-type="newsletter">';
		$html .= '<input type="hidden" name="type" value="newsletter"><input type="hidden" name="source" value="' . esc_attr( $atts['source'] ) . '">';
		$html .= '<input type="text" name="website" class="wpistic-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
		$html .= '<div class="wpistic-newsletter-row"><input type="email" name="email" placeholder="' . esc_attr__( 'Your email', 'wpistic-tour-manager' ) . '" required aria-label="' . esc_attr__( 'Email address', 'wpistic-tour-manager' ) . '"><button class="btn btn-solid" type="submit">' . esc_html__( 'Subscribe', 'wpistic-tour-manager' ) . '</button></div>';
		$html .= '<div class="wpistic-form-status" role="status" aria-live="polite"></div></form>';

		return $html;
	}
}
