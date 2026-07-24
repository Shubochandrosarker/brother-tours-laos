<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

use Wpistic\TourCore\Support\Money;

/**
 * Every payment method (Stripe, PayPal, Bank/Wise, Binance/crypto) implements this.
 * The booking flow only ever sees this interface, so a new gateway drops in without
 * touching booking logic. Implementations live in the WordPress adapter (or a Laravel one).
 */
interface PaymentGateway
{
    public function id(): string;

    public function displayName(): string;

    /**
     * Whether the gateway has the credentials/config it needs to operate.
     */
    public function isConfigured(): bool;

    public function supports(Money $amount): bool;

    /**
     * Create a hosted payment (or offline instructions) for the request.
     */
    public function createPayment(PaymentRequest $request): PaymentResult;

    /**
     * Verify and normalize an inbound webhook. Implementations MUST check the
     * signature and set PaymentEvent::$verified accordingly.
     */
    public function parseWebhook(WebhookPayload $payload): PaymentEvent;

    public function refund(RefundRequest $request): RefundResult;
}
