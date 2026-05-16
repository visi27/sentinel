<?php

declare(strict_types=1);

namespace App\Application\Outbox;

use App\Domain\Shared\DomainEvent;

/**
 * Stores domain events for later publication. The store() call runs inside
 * the same transaction as the aggregate save so the outbox row and the
 * aggregate state commit atomically.
 *
 * Fetch / mark-published operations are exercised by the outbox worker in
 * the infrastructure layer; they are intentionally not part of this
 * application port.
 */
interface OutboxRepository
{
    public function store(DomainEvent $event): void;
}
