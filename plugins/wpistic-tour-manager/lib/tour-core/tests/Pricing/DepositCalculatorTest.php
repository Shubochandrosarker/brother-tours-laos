<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Tests\Pricing;

use PHPUnit\Framework\TestCase;
use Wpistic\TourCore\Pricing\DepositCalculator;
use Wpistic\TourCore\Pricing\DepositPolicy;
use Wpistic\TourCore\Support\Currency;
use Wpistic\TourCore\Support\Money;

final class DepositCalculatorTest extends TestCase
{
    private DepositCalculator $calc;
    private Currency $usd;

    protected function setUp(): void
    {
        $this->calc = new DepositCalculator();
        $this->usd = Currency::usd();
    }

    public function testFixedDeposit(): void
    {
        $price = Money::of('2400', $this->usd);
        $policy = DepositPolicy::fixed(Money::of('500', $this->usd));

        self::assertSame('500.00', $this->calc->calculate($price, $policy)->amount());
    }

    public function testPercentDeposit(): void
    {
        $price = Money::of('2400', $this->usd);
        $policy = DepositPolicy::percent('30');

        self::assertSame('720.00', $this->calc->calculate($price, $policy)->amount());
    }

    public function testPercentRespectsMinimum(): void
    {
        $price = Money::of('1000', $this->usd);
        $policy = DepositPolicy::percent('10', Money::of('200', $this->usd));

        self::assertSame('200.00', $this->calc->calculate($price, $policy)->amount());
    }

    public function testPercentRespectsMaximum(): void
    {
        $price = Money::of('10000', $this->usd);
        $policy = DepositPolicy::percent('50', null, Money::of('3000', $this->usd));

        self::assertSame('3000.00', $this->calc->calculate($price, $policy)->amount());
    }

    public function testDepositNeverExceedsPrice(): void
    {
        $price = Money::of('400', $this->usd);
        $policy = DepositPolicy::fixed(Money::of('500', $this->usd));

        self::assertSame('400.00', $this->calc->calculate($price, $policy)->amount());
    }

    public function testBalanceAfterDeposit(): void
    {
        $price = Money::of('2400', $this->usd);
        $deposit = Money::of('720', $this->usd);

        self::assertSame('1680.00', $this->calc->balance($price, $deposit)->amount());
    }

    public function testCryptoScaleIsPreserved(): void
    {
        $price = Money::of('0.05', Currency::btc());
        $policy = DepositPolicy::percent('30');

        // 0.05 BTC * 30% = 0.015, kept at 8 decimals.
        self::assertSame('0.01500000', $this->calc->calculate($price, $policy)->amount());
    }
}
