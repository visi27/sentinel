<?php

declare(strict_types=1);

namespace App\Domain\Card\Event;

use App\Domain\Card\CardId;
use App\Domain\Shared\AbstractDomainEvent;

final class CardSuspended extends AbstractDomainEvent
{
    public function __construct(
        private readonly CardId $cardId,
        private readonly string $reason,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public function aggregateId(): string
    {
        return $this->cardId->toString();
    }

    public function aggregateType(): string
    {
        return 'Card';
    }

    public function toArray(): array
    {
        return [
            'card_id' => $this->cardId->toString(),
            'reason' => $this->reason,
        ];
    }
}
