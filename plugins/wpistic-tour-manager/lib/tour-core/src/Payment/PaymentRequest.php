<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

use Wpistic\TourCore\Support\Money;

/**
 * A request to collect a deposit or balance for a booking.
 */
final class PaymentRequest
{
    /**
     * @param array<string, scalar> $metadata
     */
    public function __construct(
        public readonly string $reference,
        public readonly Money $amount,
        public readonly string $description,
        public readonly string $type = 'deposit',
        public readonly string $returnUrl = '',
        public readonly string $cancelUrl = '',
        public readonly string $idempotencyKey = '',
        public readonly string $customerEmail = '',
        public readonly array $metadata = []
    ) {
    }
}
