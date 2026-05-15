<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Money;

use App\Domain\Money\Currency;
use App\Domain\Money\Exception\CurrencyMismatchException;
use App\Domain\Money\Exception\InvalidMoneyAmountException;
use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_money_is_constructed_with_a_non_negative_amount(): void
    {
        $money = new Money(100, Currency::USD);

        self::assertSame(100, $money->amountInMinorUnits);
        self::assertSame(Currency::USD, $money->currency);
    }

    public function test_constructor_rejects_a_negative_amount(): void
    {
        $this->expectException(InvalidMoneyAmountException::class);

        new Money(-1, Currency::USD);
    }

    public function test_usd_factory_constructs_a_dollar_amount(): void
    {
        $money = Money::usd(2500);

        self::assertSame(2500, $money->amountInMinorUnits);
        self::assertSame(Currency::USD, $money->currency);
    }

    public function test_zero_factory_constructs_zero_in_the_requested_currency(): void
    {
        $zero = Money::zero(Currency::EUR);

        self::assertTrue($zero->isZero());
        self::assertSame(Currency::EUR, $zero->currency);
    }

    public function test_add_sums_amounts_and_returns_a_new_instance(): void
    {
        $a = Money::usd(100);
        $b = Money::usd(250);

        $sum = $a->add($b);

        self::assertSame(350, $sum->amountInMinorUnits);
        // Immutability: the operands must be unchanged.
        self::assertSame(100, $a->amountInMinorUnits);
        self::assertSame(250, $b->amountInMinorUnits);
        self::assertNotSame($a, $sum);
    }

    public function test_subtract_returns_the_difference(): void
    {
        $diff = Money::usd(500)->subtract(Money::usd(150));

        self::assertSame(350, $diff->amountInMinorUnits);
    }

    public function test_subtract_rejects_a_result_that_would_be_negative(): void
    {
        $this->expectException(InvalidMoneyAmountException::class);

        Money::usd(50)->subtract(Money::usd(100));
    }

    public function test_is_less_than_compares_amounts(): void
    {
        self::assertTrue(Money::usd(100)->isLessThan(Money::usd(200)));
        self::assertFalse(Money::usd(200)->isLessThan(Money::usd(100)));
        self::assertFalse(Money::usd(100)->isLessThan(Money::usd(100)));
    }

    public function test_is_greater_than_compares_amounts(): void
    {
        self::assertTrue(Money::usd(200)->isGreaterThan(Money::usd(100)));
        self::assertFalse(Money::usd(100)->isGreaterThan(Money::usd(200)));
        self::assertFalse(Money::usd(100)->isGreaterThan(Money::usd(100)));
    }

    public function test_is_zero_only_for_zero_amounts(): void
    {
        self::assertTrue(Money::zero(Currency::USD)->isZero());
        self::assertFalse(Money::usd(1)->isZero());
    }

    public function test_equals_requires_matching_amount_and_currency(): void
    {
        self::assertTrue(Money::usd(100)->equals(Money::usd(100)));
        self::assertFalse(Money::usd(100)->equals(Money::usd(101)));
        self::assertFalse(Money::usd(100)->equals(new Money(100, Currency::EUR)));
    }

    public function test_add_rejects_cross_currency_operations(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        Money::usd(100)->add(new Money(100, Currency::EUR));
    }

    public function test_subtract_rejects_cross_currency_operations(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        Money::usd(100)->subtract(new Money(50, Currency::EUR));
    }

    public function test_comparison_rejects_cross_currency_operations(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        Money::usd(100)->isLessThan(new Money(100, Currency::EUR));
    }
}
