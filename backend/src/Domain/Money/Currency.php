<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * Supported settlement currencies, backed by their ISO 4217 alphabetic code.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
}
