<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Event;

use App\Domain\Authorization\AuthorizationId;
use App\Domain\Card\CardId;
use App\Domain\Shared\AbstractDomainEvent;

final class AuthorizationReversed extends AbstractDomainEvent
{
    public function __construct(
        private readonly AuthorizationId $authorizationId,
        private readonly CardId $cardId,
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
            'authorization_id' => $this->authorizationId->toString(),
            'card_id' => $this->cardId->toString(),
        ];
    }
}
