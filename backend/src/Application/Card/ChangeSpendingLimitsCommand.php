<?php

declare(strict_types=1);

namespace App\Application\Card;

final class ChangeSpendingLimitsCommand
{
    public function __construct(
        public readonly string $cardId,
        public readonly int $perTransactionLimit,
        public readonly int $dailyLimit,
        public readonly int $monthlyLimit,
        public readonly string $currency,
    ) {
    }
}
