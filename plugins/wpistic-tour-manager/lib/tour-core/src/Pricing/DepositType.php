<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Pricing;

/**
 * The two deposit models the client requires — both are first-class and configurable
 * (a global default, optionally overridden per tour).
 */
enum DepositType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public static function fromString(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }

    public function label(): string
    {
        return $this === self::Fixed ? 'Fixed amount' : 'Percentage of price';
    }
}
