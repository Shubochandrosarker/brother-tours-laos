<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Booking;

use Wpistic\TourManager\Connections\ConnectionsManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Front-end capture: the Booking Widget ("Request Availability"), Build My Trip,
 * and the agent form post here. Creates an inquiry, notifies via Formistic, and
 * dispatches to the generic Connections engine. Nonce-protected, honeypot, sanitized.
 */
final class CaptureController {

	public function __construct(
		private BookingService $bookings,
		private ConnectionsManager $connections
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'wpistic/v1',
			'/booking',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	public function permission( WP_REST_Request $request ): bool {
		return (bool) wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}

	public function handle( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		// Honeypot: silently accept and drop bots.
		if ( ! empty( $params['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$email = sanitize_email( (string) ( $params['email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'wpistic_invalid_email', __( 'A valid email is required.', 'wpistic-tour-manager' ), array( 'status' => 422 ) );
		}

		$type    = (string) ( $params['type'] ?? 'inquiry' );
		$allowed = array( 'booking', 'build_my_trip', 'agent', 'inquiry' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'inquiry';
		$notes   = sanitize_textarea_field( (string) ( $params['notes'] ?? ( $params['message'] ?? '' ) ) );
		$context = array();
		foreach ( array( 'notes_context' => 'Context', 'dates' => 'Dates', 'duration' => 'Duration', 'interests' => 'Interests' ) as $key => $label ) {
			if ( isset( $params[ $key ] ) && '' !== trim( (string) $params[ $key ] ) ) {
				$context[] = $label . ': ' . sanitize_text_field( (string) $params[ $key ] );
			}
		}
		if ( $context ) {
			$notes = trim( implode( "\n", $context ) . ( '' !== $notes ? "\n\n" . $notes : '' ) );
		}

		$data = array(
			'type'             => $type,
			'tour_id'          => $params['tour_id'] ?? 0,
			'departure_id'     => $params['departure_id'] ?? 0,
			'customer_name'    => $params['name'] ?? '',
			'customer_email'   => $email,
			'customer_phone'   => $params['phone'] ?? '',
			'customer_country' => $params['country'] ?? '',
			'party_adults'     => $params['adults'] ?? null,
			'party_children'   => $params['children'] ?? '',
			'hotel_pref'       => $params['hotel'] ?? '',
			'addons'           => isset( $params['addons'] ) && is_array( $params['addons'] ) ? array_map( 'sanitize_text_field', $params['addons'] ) : array(),
			'special_requests' => $notes,
			'source_url'       => $params['source_url'] ?? ( (string) $request->get_header( 'Referer' ) ),
		);

		$id      = $this->bookings->create( $data );
		if ( $id <= 0 ) {
			return new WP_Error( 'wpistic_booking_not_saved', __( 'Your request could not be saved. Please try again.', 'wpistic-tour-manager' ), array( 'status' => 500 ) );
		}
		$booking = $this->bookings->get( $id );
		if ( ! is_array( $booking ) ) {
			return new WP_Error( 'wpistic_booking_not_found', __( 'Your request could not be confirmed. Please try again.', 'wpistic-tour-manager' ), array( 'status' => 500 ) );
		}

		do_action( 'wpistic/notify', array( 'event' => 'inquiry.created', 'booking' => $booking ) );
		$this->connections->dispatch( 'inquiry.created', (array) $booking );

		return new WP_REST_Response(
			array(
				'ok'        => true,
				'reference' => $booking['reference'] ?? '',
				'message'   => __( 'Thank you — your request is with our team in Vientiane. We reply within 24 hours.', 'wpistic-tour-manager' ),
			),
			201
		);
	}
}
