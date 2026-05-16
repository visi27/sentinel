<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Outbox\OutboxReader;
use App\Application\Outbox\OutboxRecord;
use App\Application\Outbox\OutboxRepository;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Outbox\DomainEventTypeMap;
use Doctrine\DBAL\Connection;

/**
 * Doctrine-backed outbox: both the write port (used by command handlers
 * via OutboxRepository) and the read port (used by the worker via
 * OutboxReader) share a Connection. The reader uses FOR UPDATE SKIP
 * LOCKED so multiple workers can drain concurrently without conflict.
 */
final class DoctrineOutboxRepository implements OutboxRepository, OutboxReader
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

    public function fetchUnpublishedBatch(int $batchSize = 100): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, event_type, aggregate_id, aggregate_type, payload, occurred_at'
                .' FROM outbox_events WHERE published_at IS NULL'
                .' ORDER BY occurred_at ASC'
                .' LIMIT :batch FOR UPDATE SKIP LOCKED',
            ['batch' => $batchSize],
            ['batch' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(fn (array $row): OutboxRecord => $this->hydrate($row), $rows);
    }

    public function markPublished(string $eventId): void
    {
        $this->connection->executeStatement(
            'UPDATE outbox_events SET published_at = NOW(), last_error = NULL WHERE id = :id',
            ['id' => $eventId],
        );
    }

    public function markFailed(string $eventId, string $error): void
    {
        $this->connection->executeStatement(
            'UPDATE outbox_events SET publish_attempts = publish_attempts + 1,'
                .' last_attempt_at = NOW(), last_error = :error WHERE id = :id',
            ['id' => $eventId, 'error' => $error],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OutboxRecord
    {
        $payload = is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'];
        if (!is_array($payload)) {
            $payload = [];
        }

        /* @var array<string, mixed> $payload */
        return new OutboxRecord(
            eventId: (string) $row['id'],
            eventType: (string) $row['event_type'],
            aggregateId: (string) $row['aggregate_id'],
            aggregateType: (string) $row['aggregate_type'],
            payload: $payload,
            occurredAt: new \DateTimeImmutable((string) $row['occurred_at']),
        );
    }
}
