<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Event;

use App\Domain\Authorization\AuthorizationId;
use App\Domain\Card\CardId;
use App\Domain\Merchant\Merchant;
use App\Domain\Money\Money;
use App\Domain\Shared\AbstractDomainEvent;

final class CardAuthorizationApproved extends AbstractDomainEvent
{
    public function __construct(
        private readonly CardId $cardId,
        private readonly AuthorizationId $authorizationId,
        private readonly Money $amount,
        private readonly Merchant $merchant,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public function aggregateId(): string
    {
        return $this->authorizationId->toString();
    }

    public function aggregateType(): string
    {
        return 'Authorization';
    }

    public function toArray(): array
    {
        return [
            'card_id' => $this->cardId->toString(),
            'authorization_id' => $this->authorizationId->toString(),
            'amount' => $this->amount->amountInMinorUnits,
            'currency' => $this->amount->currency->value,
            'merchant' => [
                'name' => $this->merchant->name,
                'category_code' => $this->merchant->categoryCode->code,
            ],
        ];
    }
}
