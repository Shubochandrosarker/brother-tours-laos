<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Support;

/**
 * HMAC signing/verification for outbound connection webhooks and inbound gateway
 * webhooks. Constant-time comparison; never logs the secret.
 */
final class Signature
{
    public static function sign(string $payload, string $secret, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $payload, $secret);
    }

    public static function verify(string $payload, string $signature, string $secret, string $algo = 'sha256'): bool
    {
        if ('' === $secret || '' === $signature) {
            return false;
        }

        return hash_equals(self::sign($payload, $secret, $algo), $signature);
    }
}
