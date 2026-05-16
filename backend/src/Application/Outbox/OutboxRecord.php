<?php

declare(strict_types=1);

namespace App\Application\Outbox;

/**
 * The shape of an outbox row as the outbox worker sees it. Read-only data,
 * intentionally separate from DomainEvent — once a row has been persisted
 * it lives a different life from the in-memory event that produced it.
 */
final class OutboxRecord
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $aggregateId,
        public readonly string $aggregateType,
        public readonly array $payload,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
