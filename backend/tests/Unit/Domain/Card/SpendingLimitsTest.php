<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Card;

use App\Domain\Card\Exception\InvalidSpendingLimitsException;
use App\Domain\Card\SpendingLimits;
use App\Domain\Money\Currency;
use App\Domain\Money\Exception\CurrencyMismatchException;
use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class SpendingLimitsTest extends TestCase
{
    public function test_a_valid_set_of_limits_is_accepted(): void
    {
        $limits = new SpendingLimits(
            perTransaction: Money::usd(50_00),
            daily: Money::usd(100_00),
            monthly: Money::usd(500_00),
        );

        self::assertSame(50_00, $limits->perTransaction->amountInMinorUnits);
        self::assertSame(100_00, $limits->daily->amountInMinorUnits);
        self::assertSame(500_00, $limits->monthly->amountInMinorUnits);
    }

    public function test_daily_equal_to_per_transaction_is_allowed(): void
    {
        $limits = new SpendingLimits(
            perTransaction: Money::usd(100),
            daily: Money::usd(100),
            monthly: Money::usd(100),
        );

        self::assertTrue($limits->daily->equals($limits->perTransaction));
    }

    public function test_daily_below_per_transaction_is_rejected(): void
    {
        $this->expectException(InvalidSpendingLimitsException::class);

        new SpendingLimits(
            perTransaction: Money::usd(200),
            daily: Money::usd(100),
            monthly: Money::usd(500),
        );
    }

    public function test_monthly_below_daily_is_rejected(): void
    {
        $this->expectException(InvalidSpendingLimitsException::class);

        new SpendingLimits(
            perTransaction: Money::usd(50),
            daily: Money::usd(200),
            monthly: Money::usd(100),
        );
    }

    public function test_mixed_currency_limits_are_rejected_via_money(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        new SpendingLimits(
            perTransaction: Money::usd(50),
            daily: new Money(100, Currency::EUR),
            monthly: Money::usd(500),
        );
    }

    public function test_equality_compares_all_three_limits(): void
    {
        $a = new SpendingLimits(Money::usd(50), Money::usd(100), Money::usd(500));
        $b = new SpendingLimits(Money::usd(50), Money::usd(100), Money::usd(500));
        $c = new SpendingLimits(Money::usd(50), Money::usd(100), Money::usd(600));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
