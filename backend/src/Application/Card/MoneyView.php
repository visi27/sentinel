<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Money\Money;

/**
 * The wire shape of a Money value in API responses: minor units plus the
 * ISO 4217 currency code. Lives in Application because it is a presentation
 * concern — the domain's Money is unchanged.
 */
final class MoneyView
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
    }

    public static function fromMoney(Money $money): self
    {
        return new self($money->amountInMinorUnits, $money->currency->value);
    }
}
