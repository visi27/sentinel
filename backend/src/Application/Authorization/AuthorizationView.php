<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Application\Card\MoneyView;
use App\Domain\Authorization\Authorization;

/**
 * Read-side projection of a single Authorization for the admin/query API.
 */
final class AuthorizationView
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
}
