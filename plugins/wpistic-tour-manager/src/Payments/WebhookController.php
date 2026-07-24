<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Booking\BookingStatus;
use Wpistic\TourCore\Payment\WebhookPayload;
use Wpistic\TourManager\Booking\BookingService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Inbound gateway webhooks: /wp-json/wpistic/v1/webhook/{gateway}.
 * Signature is verified by the gateway; events are processed idempotently
 * (deduped in wp_wpistic_webhook_events) and reconciled against the booking.
 */
final class WebhookController {

	public function __construct(
		private GatewayManager $gateways,
		private BookingService $bookings
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'wpistic/v1',
			'/webhook/(?P<gateway>[a-z_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true', // Authenticity is the signature, not a WP user.
			)
		);
	}

	public function handle( WP_REST_Request $request ) {
		$gateway_id = sanitize_key( (string) $request->get_param( 'gateway' ) );
		$gateway    = $this->gateways->get( $gateway_id );
		if ( ! $gateway ) {
			return new WP_REST_Response( array( 'error' => 'unknown_gateway' ), 404 );
		}

		$payload = new WebhookPayload( $request->get_body(), $this->headers( $request ) );
		$event   = $gateway->parseWebhook( $payload );

		if ( ! $event->verified ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}

		if ( '' !== $event->id && $this->already_seen( $gateway_id, $event->id ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'deduplicated' => true ), 200 );
		}

		$this->record_event( $gateway_id, $event->id, $payload->rawBody );

		if ( $event->isPaid() && '' !== $event->reference ) {
			$booking = $this->bookings->get_by_reference( $event->reference );
			if ( $booking ) {
				$status = BookingStatus::tryFrom( (string) ( $booking['status'] ?? '' ) );
				if ( in_array( $status, array( BookingStatus::Confirmed, BookingStatus::BalanceDue ), true ) ) {
					$this->bookings->mark_balance_paid( (int) $booking['id'], $event->gatewayReference );
				} else {
					$this->bookings->mark_deposit_paid( (int) $booking['id'], $event->gatewayReference );
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @return array<string, string>
	 */
	private function headers( WP_REST_Request $request ): array {
		$headers = array();
		foreach ( $request->get_headers() as $name => $values ) {
			$headers[ str_replace( '_', '-', $name ) ] = is_array( $values ) ? implode( ',', $values ) : (string) $values;
		}
		return $headers;
	}

	private function already_seen( string $gateway, string $event_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpistic_webhook_events';
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE gateway = %s AND event_id = %s", $gateway, $event_id ) ); // phpcs:ignore WordPress.DB
	}

	private function record_event( string $gateway, string $event_id, string $body ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpistic_webhook_events',
			array(
				'gateway'      => $gateway,
				'event_id'     => '' !== $event_id ? $event_id : 'evt_' . md5( $body ),
				'received_at'  => current_time( 'mysql', true ),
				'processed'    => 1,
				'payload_hash' => hash( 'sha256', $body ),
			)
		);
	}
}
