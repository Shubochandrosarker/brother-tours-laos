<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Payment\PaymentEvent;
use Wpistic\TourCore\Payment\PaymentRequest;
use Wpistic\TourCore\Payment\PaymentResult;
use Wpistic\TourCore\Payment\PaymentStatus;

/**
 * Binance / crypto. Two modes:
 *  - "address": display a Binance (BEP-20) wallet address + reference; confirmed
 *    on-chain (manually in the portal, or by a checker in a later pass). Non-custodial.
 *  - "binance_pay": Binance Pay merchant API — hosted checkout + signed webhook.
 */
final class BinanceGateway extends AbstractGateway {

	private const PAY_ORDER = 'https://bpay.binanceapi.com/binancepay/openapi/v3/order';

	public function id(): string {
		return 'binance';
	}

	public function displayName(): string {
		return __( 'Binance / Crypto', 'wpistic-tour-manager' );
	}

	private function mode(): string {
		return $this->cfg( 'mode', 'address' );
	}

	public function isConfigured(): bool {
		if ( ! $this->isEnabled() ) {
			return false;
		}
		if ( 'binance_pay' === $this->mode() ) {
			return '' !== $this->cfg( 'api_key' ) && '' !== $this->cfg( 'api_secret' ) && '' !== $this->cfg( 'merchant_id' );
		}
		return '' !== $this->cfg( 'wallet_address' );
	}

	public function createPayment( PaymentRequest $request ): PaymentResult {
		if ( ! $this->isConfigured() ) {
			return $this->failed( $request->reference, __( 'Binance is not configured.', 'wpistic-tour-manager' ) );
		}
		return 'binance_pay' === $this->mode()
			? $this->create_binance_pay( $request )
			: $this->create_wallet_payment( $request );
	}

	private function create_wallet_payment( PaymentRequest $request ): PaymentResult {
		$instructions = sprintf(
			/* translators: 1: amount, 2: wallet address, 3: reference. */
			__( 'Send %1$s to the Binance (BEP-20) wallet address below. We confirm on-chain before holding your departure. Address: %2$s · Reference: %3$s', 'wpistic-tour-manager' ),
			$request->amount->format(),
			$this->cfg( 'wallet_address' ),
			$request->reference
		);

		return new PaymentResult(
			$this->id(),
			PaymentStatus::Pending,
			$request->reference,
			$request->reference,
			'',
			$instructions,
			array( 'wallet_address' => $this->cfg( 'wallet_address' ) )
		);
	}

	private function create_binance_pay( PaymentRequest $request ): PaymentResult {
		$payload = array(
			'env'             => array( 'terminalType' => 'WEB' ),
			'merchantTradeNo' => substr( preg_replace( '/[^A-Za-z0-9]/', '', $request->reference ) . (string) wp_rand( 1000, 9999 ), 0, 32 ),
			'orderAmount'     => (float) $request->amount->amount(),
			'currency'        => $request->amount->currency()->code(),
			'goods'           => array(
				'goodsType'         => '02',
				'goodsCategory'     => 'Z000',
				'referenceGoodsId'  => $request->reference,
				'goodsName'         => substr( $request->description, 0, 32 ),
			),
			'returnUrl'       => $request->returnUrl ?: home_url( '/thank-you/' ),
			'cancelUrl'       => $request->cancelUrl ?: home_url( '/' ),
		);

		$body      = (string) wp_json_encode( $payload );
		$timestamp = (string) ( time() * 1000 );
		$nonce     = wp_generate_password( 32, false );
		$signature = strtoupper( hash_hmac( 'sha512', $timestamp . "\n" . $nonce . "\n" . $body . "\n", $this->cfg( 'api_secret' ) ) );

		$response = wp_remote_post(
			self::PAY_ORDER,
			array(
				'headers' => array(
					'Content-Type'              => 'application/json',
					'BinancePay-Timestamp'      => $timestamp,
					'BinancePay-Nonce'          => $nonce,
					'BinancePay-Certificate-SN' => $this->cfg( 'api_key' ),
					'BinancePay-Signature'      => $signature,
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failed( $request->reference, $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$url  = $data['data']['checkoutUrl'] ?? ( $data['data']['universalUrl'] ?? '' );
		if ( empty( $url ) ) {
			return $this->failed( $request->reference, $data['errorMessage'] ?? __( 'Binance Pay error.', 'wpistic-tour-manager' ), is_array( $data ) ? $data : array() );
		}

		return new PaymentResult( $this->id(), PaymentStatus::Pending, $request->reference, (string) ( $data['data']['prepayId'] ?? '' ), (string) $url, '', is_array( $data ) ? $data : array() );
	}

	public function parseWebhook( \Wpistic\TourCore\Payment\WebhookPayload $payload ): PaymentEvent {
		$timestamp = $payload->header( 'BinancePay-Timestamp' );
		$nonce     = $payload->header( 'BinancePay-Nonce' );
		$signature = $payload->header( 'BinancePay-Signature' );
		$verified  = false;

		if ( '' !== $timestamp && '' !== $nonce && '' !== $signature && '' !== $this->cfg( 'api_secret' ) ) {
			$expected = strtoupper( hash_hmac( 'sha512', $timestamp . "\n" . $nonce . "\n" . $payload->rawBody . "\n", $this->cfg( 'api_secret' ) ) );
			$verified = hash_equals( $expected, $signature );
		}

		$event      = json_decode( $payload->rawBody, true );
		$biz_status = (string) ( $event['bizStatus'] ?? '' );
		$data       = is_array( $event ) ? ( json_decode( (string) ( $event['data'] ?? '' ), true ) ?: array() ) : array();
		$reference  = (string) ( $data['referenceGoodsId'] ?? ( $data['merchantTradeNo'] ?? '' ) );
		$paid       = 'PAY_SUCCESS' === $biz_status;

		return new PaymentEvent(
			(string) ( $event['bizId'] ?? '' ),
			$biz_status,
			$reference,
			$paid ? PaymentStatus::Paid : PaymentStatus::Pending,
			$verified,
			null,
			(string) ( $data['prepayId'] ?? '' )
		);
	}
}
