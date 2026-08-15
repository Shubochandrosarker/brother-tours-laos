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
 * PayPal via Orders v2 hosted approval. Webhook authenticity is confirmed by calling
 * PayPal's verify-webhook-signature endpoint with the configured webhook id.
 */
final class PayPalGateway extends AbstractGateway {

	public function id(): string {
		return 'paypal';
	}

	public function displayName(): string {
		return 'PayPal';
	}

	public function isConfigured(): bool {
		return $this->isEnabled() && '' !== $this->cfg( 'client_id' ) && '' !== $this->cfg( 'secret' );
	}

	private function base(): string {
		return 'live' === $this->cfg( 'mode', 'sandbox' ) ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
	}

	private function token(): string {
		$response = wp_remote_post(
			$this->base() . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->cfg( 'client_id' ) . ':' . $this->cfg( 'secret' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return (string) ( $data['access_token'] ?? '' );
	}

	public function createPayment( PaymentRequest $request ): PaymentResult {
		if ( ! $this->isConfigured() ) {
			return $this->failed( $request->reference, __( 'PayPal is not configured.', 'wpistic-tour-manager' ) );
		}

		$token = $this->token();
		if ( '' === $token ) {
			return $this->failed( $request->reference, __( 'PayPal authentication failed.', 'wpistic-tour-manager' ) );
		}

		$body = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(
				array(
					'reference_id' => $request->reference,
					'custom_id'    => $request->reference,
					'description'  => substr( $request->description, 0, 127 ),
					'amount'       => array(
						'currency_code' => $request->amount->currency()->code(),
						'value'         => $request->amount->amount(),
					),
				),
			),
			'application_context' => array(
				'return_url'          => $request->returnUrl ?: home_url( '/thank-you/' ),
				'cancel_url'          => $request->cancelUrl ?: home_url( '/' ),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'PAY_NOW',
			),
		);

		$response = wp_remote_post(
			$this->base() . '/v2/checkout/orders',
			array(
				'headers' => array(
					'Authorization'    => 'Bearer ' . $token,
					'Content-Type'     => 'application/json',
					'PayPal-Request-Id' => $request->idempotencyKey ?: $request->reference,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failed( $request->reference, $response->get_error_message() );
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$approve = '';
		foreach ( (array) ( $data['links'] ?? array() ) as $link ) {
			if ( 'approve' === ( $link['rel'] ?? '' ) ) {
				$approve = (string) $link['href'];
			}
		}

		if ( '' === $approve ) {
			return $this->failed( $request->reference, $data['message'] ?? __( 'PayPal error.', 'wpistic-tour-manager' ), is_array( $data ) ? $data : array() );
		}

		return new PaymentResult( $this->id(), PaymentStatus::Pending, $request->reference, (string) ( $data['id'] ?? '' ), $approve, '', is_array( $data ) ? $data : array() );
	}

	public function parseWebhook( WebhookPayload $payload ): PaymentEvent {
		$event     = json_decode( $payload->rawBody, true );
		$type      = (string) ( $event['event_type'] ?? '' );
		$resource  = is_array( $event ) ? ( $event['resource'] ?? array() ) : array();
		$reference = (string) ( $resource['custom_id'] ?? ( $resource['purchase_units'][0]['reference_id'] ?? '' ) );
		$verified  = $this->verify_signature( $payload, $event );
		$paid      = in_array( $type, array( 'PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.COMPLETED' ), true );

		return new PaymentEvent(
			(string) ( $event['id'] ?? '' ),
			$type,
			$reference,
			$paid ? PaymentStatus::Paid : PaymentStatus::Pending,
			$verified,
			null,
			(string) ( $resource['id'] ?? '' )
		);
	}

	private function verify_signature( WebhookPayload $payload, $event ): bool {
		$webhook_id = $this->cfg( 'webhook_id' );
		if ( '' === $webhook_id ) {
			return false;
		}
		$token = $this->token();
		if ( '' === $token ) {
			return false;
		}

		$body = array(
			'transmission_id'   => $payload->header( 'Paypal-Transmission-Id' ),
			'transmission_time' => $payload->header( 'Paypal-Transmission-Time' ),
			'cert_url'          => $payload->header( 'Paypal-Cert-Url' ),
			'auth_algo'         => $payload->header( 'Paypal-Auth-Algo' ),
			'transmission_sig'  => $payload->header( 'Paypal-Transmission-Sig' ),
			'webhook_id'        => $webhook_id,
			'webhook_event'     => $event,
		);

		$response = wp_remote_post(
			$this->base() . '/v1/notifications/verify-webhook-signature',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['verification_status'] ) && 'SUCCESS' === $data['verification_status'];
	}

	public function refund( RefundRequest $request ): RefundResult {
		if ( ! $this->isConfigured() ) {
			return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, __( 'PayPal is not configured.', 'wpistic-tour-manager' ) );
		}
		$token = $this->token();
		if ( '' === $token ) {
			return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, __( 'PayPal authentication failed.', 'wpistic-tour-manager' ) );
		}

		$response = wp_remote_post(
			$this->base() . '/v2/payments/captures/' . rawurlencode( $request->gatewayReference ) . '/refund',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'amount' => array(
							'value'         => $request->amount->amount(),
							'currency_code' => $request->amount->currency()->code(),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = isset( $data['status'] ) && in_array( $data['status'], array( 'COMPLETED', 'PENDING' ), true );

		return new RefundResult( $ok, (string) ( $data['id'] ?? '' ), PaymentStatus::Refunded, $ok ? '' : ( $data['message'] ?? 'Refund failed' ), is_array( $data ) ? $data : array() );
	}
}
