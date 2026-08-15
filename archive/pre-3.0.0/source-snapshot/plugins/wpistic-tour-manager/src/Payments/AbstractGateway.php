<?php

declare(strict_types=1);

namespace Wpistic\TourManager\Payments;

use Wpistic\TourCore\Payment\PaymentEvent;
use Wpistic\TourCore\Payment\PaymentGateway;
use Wpistic\TourCore\Payment\PaymentResult;
use Wpistic\TourCore\Payment\PaymentStatus;
use Wpistic\TourCore\Payment\RefundRequest;
use Wpistic\TourCore\Payment\RefundResult;
use Wpistic\TourCore\Payment\WebhookPayload;
use Wpistic\TourCore\Support\Money;
use Wpistic\TourManager\Admin\Settings;

/**
 * Base for the WordPress gateway implementations. Reads its config from settings;
 * subclasses implement createPayment() and (where relevant) parseWebhook()/refund().
 */
abstract class AbstractGateway implements PaymentGateway {

	/** @var array<string, mixed> */
	protected array $config;

	public function __construct() {
		$this->config = Settings::gateway( $this->id() );
	}

	public function isEnabled(): bool {
		return ! empty( $this->config['enabled'] );
	}

	public function supports( Money $amount ): bool {
		return $amount->isPositive();
	}

	protected function cfg( string $key, string $default = '' ): string {
		return (string) ( $this->config[ $key ] ?? $default );
	}

	protected function failed( string $reference, string $message, array $raw = array() ): PaymentResult {
		return new PaymentResult( $this->id(), PaymentStatus::Failed, $reference, '', '', $message, $raw );
	}

	public function parseWebhook( WebhookPayload $payload ): PaymentEvent {
		return new PaymentEvent( '', 'unsupported', '', PaymentStatus::Pending, false );
	}

	public function refund( RefundRequest $request ): RefundResult {
		return new RefundResult( false, $request->gatewayReference, PaymentStatus::Refunded, 'Manual refund required for this gateway.' );
	}
}
