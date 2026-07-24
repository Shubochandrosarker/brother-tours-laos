<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Pricing;

use Wpistic\TourCore\Support\Money;

/**
 * Resolves the deposit due from a tour price and a deposit policy.
 *
 * Pure logic: no I/O, no framework, no side effects. Unit-tested in isolation
 * and reused verbatim by both the WordPress plugin and a future Laravel app.
 */
final class DepositCalculator
{
    public function calculate(Money $price, DepositPolicy $policy): Money
    {
        $deposit = match ($policy->type()) {
            DepositType::Fixed => Money::of($policy->value(), $price->currency()),
            DepositType::Percent => $price->percentage($policy->value()),
        };

        // A deposit can never exceed the full price.
        if ($deposit->isGreaterThan($price)) {
            $deposit = $price;
        }

        $minimum = $policy->minimum();
        if ($minimum !== null && $deposit->isLessThan($minimum)) {
            $deposit = $minimum;
        }

        $maximum = $policy->maximum();
        if ($maximum !== null && $deposit->isGreaterThan($maximum)) {
            $deposit = $maximum;
        }

        return $deposit;
    }

    /** The balance still owed once the deposit is paid. */
    public function balance(Money $price, Money $deposit): Money
    {
        return $price->subtract($deposit);
    }
}
