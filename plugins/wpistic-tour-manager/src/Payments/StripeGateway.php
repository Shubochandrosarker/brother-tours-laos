<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Payment\PaymentEvent;
use Wpistic\TourCore\Payment\PaymentRequest;
use Wpistic\TourCore\Payment\PaymentResult;
use Wpistic\TourCore\Payment\PaymentStatus;
use Wpistic\TourCore\Payment\RefundRequest;
use Wpistic\TourCore\Payment\RefundResult;
use Wpistic\TourCore\Payment\WebhookPayload;

/**
 * Stripe via hosted Checkout Sessions (PCI SAQ A — no raw card data here).
 * Idempotency key on create; webhook signature verified with the signing secret.
 */
final class StripeGateway extends AbstractGateway {

	private const API = 'https://api.stripe.com/v1';

	public function id(): string {
		return 'stripe';
	}

	public function displayName(): string {
		return 'Stripe';
	}

	public function isConfigured(): bool {
		return $this->isEnabled() && '' !== $this->cfg( 'secret_key' );
	}

	public function createPayment( PaymentRequest $request ): PaymentResult {
		if ( ! $this->isConfigured() ) {
			return $this->failed( $request->reference, __( 'Stripe is not configured.', 'wpistic-tour-manager' ) );
		}

		$body = array(
			'mode'                                          => 'payment',
			'success_url'                                   => $request->returnUrl ?: home_url( '/thank-you/' ),
			'cancel_url'                                    => $request->cancelUrl ?: home_url( '/' ),
			'client_reference_id'                           => $request->reference,
			'metadata[reference]'                           => $request->reference,
			'metadata[type]'                                => $request->type,
			'line_items[0][quantity]'                       => 1,
			'line_items[0][price_data][currency]'           => strtolower( $request->amount->currency()->code() ),
			'line_items[0][price_data][unit_amount]'        => $request->amount->minorUnits(),
			'line_items[0][price_data][product_data][name]' => $request->description,
		);
		if ( '' !== $request->customerEmail ) {
			$body['customer_email'] = $request->customerEmail;
		}

		$response = wp_remote_post(
			self::API . '/checkout/sessions',
			array(
				'headers' => array(
					'Authorization'   => 'Bearer ' . $this->cfg( 'secret_key' ),
					'Idempotency-Key' => $request->idempotencyKey ?: $request->reference,
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failed( $request->reference, $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['url'] ) ) {
			return $this->failed( $request->reference, $data['error']['message'] ?? __( 'Stripe error.', 'wpistic-tour-manager' ), is_array( $data ) ? $data : array() );
		}

		return new PaymentResult(
			$this->id(),
			PaymentStatus::Pending,
			$request->reference,
			(string) ( $data['id'] ?? '' ),
			(string) $data['url'],
			'',
			is_array( $data ) ? $data : array()
		);
	}

	public function parseWebhook( WebhookPayload $payload ): PaymentEvent {
		$verified = $this->verify_signature( $payload->rawBody, $payload->header( 'Stripe-Signature' ), $this->cfg( 'webhook_secret' ) );

		$event     = json_decode( $payload->rawBody, true );
		$type      = (string) ( $event['type'] ?? '' );
		$object    = is_array( $event ) ? ( $event['data']['object'] ?? array() ) : array();
		$reference = (string) ( $object['client_reference_id'] ?? ( $object['metadata']['reference'] ?? '' ) );

		$paid_types = array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' );
		$status     = in_array( $type, $paid_types, true ) ? PaymentStatus::Paid : PaymentStatus::Pending;

		return new PaymentEvent(
			(string) ( $event['id'] ?? '' ),
			$type,
			$reference,
			$status,
			$verified,
			null,
			(string) ( $object['payment_intent'] ?? ( $object['id'] ?? '' ) )
		);
	}

	private function verify_signature( string $payload, string $header, string $secret ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$parts = array();
		foreach ( explode( ',', $header ) as $pair ) {
			$kv = explode( '=', $pair, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ trim( $kv[0] ) ][] = trim( $kv[1] );
			}
		}

		$timestamp = $parts['t'][0] ?? '';
		$signatures = $parts['v1'] ?? array();
		if ( '' === $timestamp || empty( $signatures ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	public function refund( RefundRequest $request ): RefundResult {
		if ( ! $this->isConfigured() ) {
			return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, __( 'Stripe is not configured.', 'wpistic-tour-manager' ) );
		}

		$response = wp_remote_post(
			self::API . '/refunds',
			array(
				'headers' => array(
					'Authorization'   => 'Bearer ' . $this->cfg( 'secret_key' ),
					'Idempotency-Key' => $request->idempotencyKey ?: ( 'rf_' . $request->gatewayReference ),
				),
				'body'    => array(
					'payment_intent' => $request->gatewayReference,
					'amount'         => $request->amount->minorUnits(),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = isset( $data['status'] ) && in_array( $data['status'], array( 'succeeded', 'pending' ), true );

		return new RefundResult( $ok, (string) ( $data['id'] ?? '' ), PaymentStatus::Refunded, $ok ? '' : ( $data['error']['message'] ?? 'Refund failed' ), is_array( $data ) ? $data : array() );
	}
}
