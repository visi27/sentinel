<?php

declare(strict_types=1);

namespace App\Domain\Merchant\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidMerchantCategoryCodeException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf(
            'Merchant category code must be exactly 4 digits; got "%s".',
            $code,
        ));
    }
}
