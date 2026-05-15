<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Base for aggregate roots. Aggregates record the events they raise; the
 * application layer pulls them with releaseEvents() after persistence and
 * hands them to the outbox.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    protected function raise(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Returns the events recorded since the last call and clears the buffer,
     * so events are dispatched exactly once.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
