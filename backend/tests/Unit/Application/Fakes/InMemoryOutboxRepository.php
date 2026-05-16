<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Outbox\OutboxRepository;
use App\Domain\Shared\DomainEvent;

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function store(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
