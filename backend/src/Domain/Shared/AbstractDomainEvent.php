<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Provides the envelope concerns (event id, occurred-at) shared by every
 * domain event. The aggregate supplies occurredAt explicitly so event
 * timestamps stay deterministic and testable.
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly string $eventId;

    public function __construct(private readonly \DateTimeImmutable $occurredAt)
    {
        $this->eventId = Uuid::v7();
    }

    final public function eventId(): string
    {
        return $this->eventId;
    }

    final public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
