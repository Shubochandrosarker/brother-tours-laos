<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

final class RefundResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $gatewayReference = '',
        public readonly PaymentStatus $status = PaymentStatus::Refunded,
        public readonly string $message = '',
        public readonly array $raw = []
    ) {
    }
}
