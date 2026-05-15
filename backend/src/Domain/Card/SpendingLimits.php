<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Card\Exception\InvalidSpendingLimitsException;
use App\Domain\Money\Money;

/**
 * The three spending ceilings applied to a card. The ordering invariant
 * (per-transaction <= daily <= monthly) is enforced at construction; cross-
 * currency limits are rejected by Money's own comparison guard.
 */
final class SpendingLimits
{
    public function __construct(
        public readonly Money $perTransaction,
        public readonly Money $daily,
        public readonly Money $monthly,
    ) {
        if ($this->daily->isLessThan($perTransaction)) {
            throw InvalidSpendingLimitsException::dailyBelowPerTransaction();
        }

        if ($this->monthly->isLessThan($daily)) {
            throw InvalidSpendingLimitsException::monthlyBelowDaily();
        }
    }

    public function equals(self $other): bool
    {
        return $this->perTransaction->equals($other->perTransaction)
            && $this->daily->equals($other->daily)
            && $this->monthly->equals($other->monthly);
    }
}
