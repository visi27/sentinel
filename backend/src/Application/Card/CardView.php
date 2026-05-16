<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\Card;

/**
 * Read-side projection of a Card, ready to serialize to JSON. Constructed
 * either from the live aggregate or, in the production query path, directly
 * from a database row — see CardQueryService.
 */
final class CardView implements \JsonSerializable
{
    /**
     * @param list<string> $allowedMerchantCategories
     */
    public function __construct(
        public readonly string $id,
        public readonly string $cardholderId,
        public readonly string $status,
        public readonly SpendingLimitsView $spendingLimits,
        public readonly MoneyView $availableBalance,
        public readonly MoneyView $dailySpend,
        public readonly MoneyView $monthlySpend,
        public readonly array $allowedMerchantCategories,
        public readonly \DateTimeImmutable $issuedAt,
        public readonly ?\DateTimeImmutable $activatedAt,
        public readonly ?\DateTimeImmutable $closedAt,
    ) {
    }

    public static function fromCard(Card $card): self
    {
        $codes = array_map(
            static fn ($mcc): string => $mcc->code,
            $card->allowedMerchantCategoryCodes(),
        );

        return new self(
            $card->id()->toString(),
            $card->cardholderId()->toString(),
            $card->status()->value,
            SpendingLimitsView::fromSpendingLimits($card->spendingLimits()),
            MoneyView::fromMoney($card->availableBalance()),
            MoneyView::fromMoney($card->dailySpend()),
            MoneyView::fromMoney($card->monthlySpend()),
            $codes,
            $card->issuedAt(),
            $card->activatedAt(),
            $card->closedAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'cardholder_id' => $this->cardholderId,
            'status' => $this->status,
            'spending_limits' => $this->spendingLimits,
            'available_balance' => $this->availableBalance,
            'daily_spend' => $this->dailySpend,
            'monthly_spend' => $this->monthlySpend,
            'allowed_merchant_categories' => $this->allowedMerchantCategories,
            'issued_at' => self::formatUtc($this->issuedAt),
            'activated_at' => self::formatUtc($this->activatedAt),
            'closed_at' => self::formatUtc($this->closedAt),
        ];
    }

    private static function formatUtc(?\DateTimeImmutable $dateTime): ?string
    {
        return $dateTime?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
