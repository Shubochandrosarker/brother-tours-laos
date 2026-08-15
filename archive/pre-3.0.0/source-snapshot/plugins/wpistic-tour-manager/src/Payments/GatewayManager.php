<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Payment\PaymentGateway;

/**
 * Registry of payment gateways. Pluggable: third parties add gateways via the
 * `wpistic_tm_gateways` filter without touching booking logic.
 */
final class GatewayManager {

	/** @var array<string, PaymentGateway> */
	private array $gateways = array();

	public function register(): void {
		$built_in = array(
			new BankWiseGateway(),
			new StripeGateway(),
			new PayPalGateway(),
			new BinanceGateway(),
		);

		$map = array();
		foreach ( $built_in as $gateway ) {
			$map[ $gateway->id() ] = $gateway;
		}

		/**
		 * Filter the registered payment gateways.
		 *
		 * @param array<string, PaymentGateway> $map
		 */
		$this->gateways = apply_filters( 'wpistic_tm_gateways', $map );
	}

	public function get( string $id ): ?PaymentGateway {
		return $this->gateways[ $id ] ?? null;
	}

	/**
	 * @return array<string, PaymentGateway>
	 */
	public function all(): array {
		return $this->gateways;
	}

	/**
	 * @return array<string, PaymentGateway>
	 */
	public function enabled(): array {
		return array_filter(
			$this->gateways,
			static function ( PaymentGateway $gateway ): bool {
				return $gateway->isConfigured();
			}
		);
	}
}
