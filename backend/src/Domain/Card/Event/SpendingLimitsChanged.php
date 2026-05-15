<?php

declare(strict_types=1);

namespace App\Domain\Card\Event;

use App\Domain\Card\CardId;
use App\Domain\Card\SpendingLimits;
use App\Domain\Shared\AbstractDomainEvent;

final class SpendingLimitsChanged extends AbstractDomainEvent
{
    public function __construct(
        private readonly CardId $cardId,
        private readonly SpendingLimits $limits,
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
            'per_transaction' => $this->limits->perTransaction->amountInMinorUnits,
            'daily' => $this->limits->daily->amountInMinorUnits,
            'monthly' => $this->limits->monthly->amountInMinorUnits,
            'currency' => $this->limits->perTransaction->currency->value,
        ];
    }
}
