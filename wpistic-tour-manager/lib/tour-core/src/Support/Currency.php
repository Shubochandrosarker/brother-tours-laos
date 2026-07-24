<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Support;

use InvalidArgumentException;

/**
 * An immutable currency descriptor that works for both fiat and crypto.
 *
 * `scale` is the number of decimal places the currency settles in
 * (USD = 2, USDT = 6, BTC = 8, BNB = 18). The core never assumes "two decimals",
 * which is what lets the same pricing logic handle dollars and crypto correctly.
 */
final class Currency
{
    public const TYPE_FIAT = 'fiat';
    public const TYPE_CRYPTO = 'crypto';

    private function __construct(
        private readonly string $code,
        private readonly int $scale,
        private readonly string $type,
        private readonly string $symbol
    ) {
    }

    public static function of(string $code, int $scale, string $type, string $symbol = ''): self
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            throw new InvalidArgumentException('Currency code cannot be empty.');
        }

        if ($scale < 0 || $scale > 18) {
            throw new InvalidArgumentException("Unsupported currency scale: {$scale}.");
        }

        if (!in_array($type, [self::TYPE_FIAT, self::TYPE_CRYPTO], true)) {
            throw new InvalidArgumentException("Unknown currency type: {$type}.");
        }

        return new self($code, $scale, $type, $symbol === '' ? $code : $symbol);
    }

    public static function usd(): self
    {
        return self::of('USD', 2, self::TYPE_FIAT, '$');
    }

    public static function btc(): self
    {
        return self::of('BTC', 8, self::TYPE_CRYPTO);
    }

    public static function usdt(): self
    {
        return self::of('USDT', 6, self::TYPE_CRYPTO);
    }

    public static function bnb(): self
    {
        return self::of('BNB', 18, self::TYPE_CRYPTO);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function isCrypto(): bool
    {
        return $this->type === self::TYPE_CRYPTO;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->scale === $other->scale;
    }
}
