<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Pricing;

use InvalidArgumentException;
use Wpistic\TourCore\Support\Money;

/**
 * A configurable deposit rule. Supports BOTH a fixed amount and a percentage,
 * each with an optional floor and ceiling. The site's global default is one of
 * these; an individual tour may carry its own override.
 */
final class DepositPolicy
{
    public function __construct(
        private readonly DepositType $type,
        private readonly string $value,
        private readonly ?Money $minimum = null,
        private readonly ?Money $maximum = null
    ) {
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid deposit value: {$value}.");
        }

        if ($type === DepositType::Percent && (float) $value > 100.0) {
            throw new InvalidArgumentException('Deposit percentage cannot exceed 100.');
        }

        if ($minimum !== null && $maximum !== null && $maximum->isLessThan($minimum)) {
            throw new InvalidArgumentException('Deposit maximum cannot be below the minimum.');
        }
    }

    public static function fixed(Money $amount, ?Money $min = null, ?Money $max = null): self
    {
        return new self(DepositType::Fixed, $amount->amount(), $min, $max);
    }

    public static function percent(string $percent, ?Money $min = null, ?Money $max = null): self
    {
        return new self(DepositType::Percent, $percent, $min, $max);
    }

    public function type(): DepositType
    {
        return $this->type;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function minimum(): ?Money
    {
        return $this->minimum;
    }

    public function maximum(): ?Money
    {
        return $this->maximum;
    }
}
