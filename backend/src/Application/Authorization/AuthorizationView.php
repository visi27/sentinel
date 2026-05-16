<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Card\MoneyView;
use App\Domain\Authorization\Authorization;

/**
 * Read-side projection of a single Authorization for the admin/query API.
 */
final class AuthorizationView implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $cardId,
        public readonly string $processorAuthId,
        public readonly MoneyView $amount,
        public readonly string $merchantName,
        public readonly string $merchantCategoryCode,
        public readonly string $status,
        public readonly ?string $declineReason,
        public readonly \DateTimeImmutable $requestedAt,
        public readonly ?\DateTimeImmutable $decidedAt,
        public readonly ?\DateTimeImmutable $reversedAt,
    ) {
    }

    public static function fromAuthorization(Authorization $authorization): self
    {
        return new self(
            $authorization->id()->toString(),
            $authorization->cardId()->toString(),
            $authorization->processorAuthId(),
            MoneyView::fromMoney($authorization->amount()),
            $authorization->merchant()->name,
            $authorization->merchant()->categoryCode->code,
            $authorization->status()->value,
            $authorization->declineReason()?->value,
            $authorization->requestedAt(),
            $authorization->decidedAt(),
            $authorization->reversedAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->cardId,
            'processor_auth_id' => $this->processorAuthId,
            'amount' => $this->amount,
            'merchant' => [
                'name' => $this->merchantName,
                'category_code' => $this->merchantCategoryCode,
            ],
            'status' => $this->status,
            'decline_reason' => $this->declineReason,
            'requested_at' => self::formatUtc($this->requestedAt),
            'decided_at' => self::formatUtc($this->decidedAt),
            'reversed_at' => self::formatUtc($this->reversedAt),
        ];
    }

    private static function formatUtc(?\DateTimeImmutable $dateTime): ?string
    {
        return $dateTime?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
