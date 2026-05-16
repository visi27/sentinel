<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\SpendingLimits;

final class SpendingLimitsView implements \JsonSerializable
{
    public function __construct(
        public readonly MoneyView $perTransaction,
        public readonly MoneyView $daily,
        public readonly MoneyView $monthly,
    ) {
    }

    public static function fromSpendingLimits(SpendingLimits $limits): self
    {
        return new self(
            MoneyView::fromMoney($limits->perTransaction),
            MoneyView::fromMoney($limits->daily),
            MoneyView::fromMoney($limits->monthly),
        );
    }

    /**
     * @return array{per_transaction: MoneyView, daily: MoneyView, monthly: MoneyView}
     */
    public function jsonSerialize(): array
    {
        return [
            'per_transaction' => $this->perTransaction,
            'daily' => $this->daily,
            'monthly' => $this->monthly,
        ];
    }
}
