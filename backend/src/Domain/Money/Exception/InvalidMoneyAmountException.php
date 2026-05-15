<?php

declare(strict_types=1);

namespace App\Domain\Money\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidMoneyAmountException extends DomainException
{
    public static function negative(int $amountInMinorUnits): self
    {
        return new self(sprintf(
            'Money cannot be negative; got %d minor units.',
            $amountInMinorUnits,
        ));
    }
}
