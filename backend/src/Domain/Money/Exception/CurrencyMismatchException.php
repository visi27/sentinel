<?php

declare(strict_types=1);

namespace App\Domain\Money\Exception;

use App\Domain\Money\Currency;
use App\Domain\Shared\Exception\DomainException;

final class CurrencyMismatchException extends DomainException
{
    public static function between(Currency $left, Currency $right): self
    {
        return new self(sprintf(
            'Cannot operate on Money of different currencies: %s and %s.',
            $left->value,
            $right->value,
        ));
    }
}
