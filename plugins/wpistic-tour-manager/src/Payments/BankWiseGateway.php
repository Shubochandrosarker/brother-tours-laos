<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Payment\PaymentRequest;
use Wpistic\TourCore\Payment\PaymentResult;
use Wpistic\TourCore\Payment\PaymentStatus;
use Wpistic\TourCore\Payment\RefundRequest;
use Wpistic\TourCore\Payment\RefundResult;

/**
 * Offline bank transfer / Wise. Fully working: returns payment instructions with a
 * unique reference; an admin marks the booking paid in the portal. No funds custody.
 */
final class BankWiseGateway extends AbstractGateway {

	public function id(): string {
		return 'bank';
	}

	public function displayName(): string {
		return __( 'Bank transfer / Wise', 'wpistic-tour-manager' );
	}

	public function isConfigured(): bool {
		return $this->isEnabled() && '' !== trim( $this->cfg( 'instructions' ) );
	}

	public function createPayment( PaymentRequest $request ): PaymentResult {
		$instructions = $this->cfg( 'instructions' );
		$instructions .= "\n\n" . sprintf(
			/* translators: 1: amount, 2: reference. */
			__( 'Amount due: %1$s. Please quote reference %2$s with your transfer.', 'wpistic-tour-manager' ),
			$request->amount->format(),
			$request->reference
		);

		return new PaymentResult(
			$this->id(),
			PaymentStatus::Pending,
			$request->reference,
			$request->reference,
			'',
			$instructions
		);
	}

	public function refund( RefundRequest $request ): RefundResult {
		// Bank refunds are processed by hand; record the intent.
		return new RefundResult( true, $request->gatewayReference, PaymentStatus::Refunded, __( 'Bank refund recorded — process manually.', 'wpistic-tour-manager' ) );
	}
}
