<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

use Wpistic\TourCore\Support\Money;

/**
 * A normalized, signature-verified payment event parsed from a webhook.
 * `id` is the gateway's event id and is used for idempotent processing.
 */
final class PaymentEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $reference,
        public readonly PaymentStatus $status,
        public readonly bool $verified,
        public readonly ?Money $amount = null,
        public readonly string $gatewayReference = ''
    ) {
    }

    public function isPaid(): bool
    {
        return $this->verified && PaymentStatus::Paid === $this->status;
    }
}
