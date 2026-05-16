<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Outbox\OutboxRepository;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Outbox\DomainEventTypeMap;
use Doctrine\DBAL\Connection;

final class DoctrineOutboxRepository implements OutboxRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function store(DomainEvent $event): void
    {
        // INSERT runs on the same Connection used by the EntityManager, so
        // a TransactionManager-wrapped block keeps this row and the
        // aggregate write inside a single commit boundary.
        $this->connection->insert('outbox_events', [
            'id' => $event->eventId(),
            'event_type' => DomainEventTypeMap::typeFor($event),
            'aggregate_id' => $event->aggregateId(),
            'aggregate_type' => $event->aggregateType(),
            'payload' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
