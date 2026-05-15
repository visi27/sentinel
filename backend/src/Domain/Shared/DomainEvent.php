<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * A fact that has happened in the domain. Events are collected on the
 * aggregate that raised them and released by the application layer for
 * storage in the outbox.
 */
interface DomainEvent
{
    public function eventId(): string;

    public function occurredAt(): \DateTimeImmutable;

    public function aggregateId(): string;

    public function aggregateType(): string;

    /**
     * The event-specific payload, used as the body of the outbox record and
     * the outbound webhook. The envelope fields (event id, occurred-at) are
     * read separately via the methods above.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
