<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Payment;

/**
 * The raw inbound webhook (exact body + headers) handed to a gateway for
 * signature verification. The raw body must be passed unaltered.
 */
final class WebhookPayload
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $rawBody,
        public readonly array $headers = []
    ) {
    }

    public function header(string $name): string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $needle) {
                return (string) $value;
            }
        }

        return '';
    }
}
