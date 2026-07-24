<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Support;

use InvalidArgumentException;

/**
 * Immutable money amount with exact decimal arithmetic (bcmath, never floats),
 * so crypto amounts with many decimal places stay correct to the last digit.
 */
final class Money
{
    /** Normalized decimal string at the currency's scale, e.g. "1500.00". */
    private readonly string $amount;

    private function __construct(string $amount, private readonly Currency $currency)
    {
        $this->amount = self::round($amount, $currency->scale());
    }

    public static function of(string|int $amount, Currency $currency): self
    {
        return new self((string) $amount, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self('0', $currency);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcadd($this->amount, $other->amount, $this->scale()), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcsub($this->amount, $other->amount, $this->scale()), $this->currency);
    }

    /** Multiply by a plain decimal factor, e.g. "0.30" for 30%. */
    public function multipliedBy(string $factor): self
    {
        return new self(bcmul($this->amount, $factor, $this->scale() + 8), $this->currency);
    }

    /** A percentage of this amount, e.g. percentage("30") returns 30% of it. */
    public function percentage(string $percent): self
    {
        return $this->multipliedBy(bcdiv($percent, '100', 12));
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->scale()) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', $this->scale()) === 1;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, $this->scale()) === 1;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, $this->scale()) === -1;
    }

    /** Integer smallest-unit string (e.g. cents) — the shape most gateways expect. */
    public function minorUnits(): string
    {
        return bcmul($this->amount, bcpow('10', (string) $this->currency->scale()), 0);
    }

    public function format(): string
    {
        return trim($this->currency->symbol() . ' ' . $this->amount . ' ' . $this->currency->code());
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->currency->code();
    }

    private function scale(): int
    {
        return $this->currency->scale();
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency->code()} vs {$other->currency->code()}."
            );
        }
    }

    /** Round-half-up to the given scale using exact string math (bcmath has no round()). */
    private static function round(string $value, int $scale): string
    {
        $value = trim($value);

        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid money amount: {$value}.");
        }

        if (!str_contains($value, '.')) {
            return bcadd($value, '0', $scale);
        }

        $negative = str_starts_with($value, '-');
        $magnitude = $negative ? substr($value, 1) : $value;
        [$int, $frac] = explode('.', $magnitude, 2);

        if (strlen($frac) <= $scale) {
            return bcadd($value, '0', $scale);
        }

        $truncated = $scale === 0 ? $int : $int . '.' . substr($frac, 0, $scale);
        $roundDigit = (int) $frac[$scale];

        if ($roundDigit >= 5) {
            $increment = $scale === 0 ? '1' : bcdiv('1', bcpow('10', (string) $scale), $scale);
            $truncated = bcadd($truncated, $increment, $scale);
        } else {
            $truncated = bcadd($truncated, '0', $scale);
        }

        if ($negative && bccomp($truncated, '0', $scale) !== 0) {
            return '-' . $truncated;
        }

        return $truncated;
    }
}
