<?php

declare(strict_types=1);

namespace App\Application\Card;

final class IssueCardCommand
{
    /**
     * @param list<string> $allowedMerchantCategoryCodes
     */
    public function __construct(
        public readonly string $cardholderId,
        public readonly int $perTransactionLimit,
        public readonly int $dailyLimit,
        public readonly int $monthlyLimit,
        public readonly int $initialBalance,
        public readonly string $currency,
        public readonly array $allowedMerchantCategoryCodes,
    ) {
    }
}
