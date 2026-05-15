<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Domain\Money\Exception\CurrencyMismatchException;
use App\Domain\Money\Exception\InvalidMoneyAmountException;

/**
 * An immutable monetary amount stored in minor units (e.g. cents) to avoid
 * floating-point error. All arithmetic returns new instances and rejects
 * cross-currency operations.
 */
final class Money
{
    public function __construct(
        public readonly int $amountInMinorUnits,
        public readonly Currency $currency,
    ) {
        if ($amountInMinorUnits < 0) {
            throw InvalidMoneyAmountException::negative($amountInMinorUnits);
        }
    }

    public static function usd(int $cents): self
    {
        return new self($cents, Currency::USD);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInMinorUnits + $other->amountInMinorUnits, $this->currency);
    }

    /**
     * Subtraction rejects a negative result via the constructor invariant:
     * Money has no concept of debt.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInMinorUnits - $other->amountInMinorUnits, $this->currency);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountInMinorUnits < $other->amountInMinorUnits;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountInMinorUnits > $other->amountInMinorUnits;
    }

    public function isZero(): bool
    {
        return 0 === $this->amountInMinorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->amountInMinorUnits === $other->amountInMinorUnits
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }
}
