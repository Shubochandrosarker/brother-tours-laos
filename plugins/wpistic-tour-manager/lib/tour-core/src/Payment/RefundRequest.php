<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

use Wpistic\TourCore\Support\Money;

final class RefundRequest
{
    public function __construct(
        public readonly string $gatewayReference,
        public readonly Money $amount,
        public readonly string $reason = '',
        public readonly string $idempotencyKey = ''
    ) {
    }
}
