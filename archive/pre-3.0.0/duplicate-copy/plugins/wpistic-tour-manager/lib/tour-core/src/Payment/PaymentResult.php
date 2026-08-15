<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

/**
 * The outcome of creating a payment. Hosted gateways return a redirect URL;
 * the offline bank gateway returns human-readable instructions.
 */
final class PaymentResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $gatewayId,
        public readonly PaymentStatus $status,
        public readonly string $reference,
        public readonly string $gatewayReference = '',
        public readonly string $redirectUrl = '',
        public readonly string $instructions = '',
        public readonly array $raw = []
    ) {
    }

    public function requiresRedirect(): bool
    {
        return '' !== $this->redirectUrl;
    }
}
