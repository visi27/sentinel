<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Card\CardholderId;
use App\Domain\Card\CardId;
use App\Domain\Card\Event\CardIssued;
use App\Infrastructure\Persistence\Doctrine\DoctrineOutboxRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOutboxRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private DoctrineOutboxRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->repository = new DoctrineOutboxRepository($this->connection);
    }

    public function test_storing_an_event_writes_a_row_with_the_wire_type_and_payload(): void
    {
        $event = new CardIssued(
            CardId::generate(),
            CardholderId::generate(),
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );

        $this->repository->store($event);

        $row = $this->connection->fetchAssociative(
            'SELECT id, event_type, aggregate_id, aggregate_type, payload FROM outbox_events WHERE id = :id',
            ['id' => $event->eventId()],
        );
        self::assertNotFalse($row);
        self::assertSame('card.issued', $row['event_type']);
        self::assertSame('Card', $row['aggregate_type']);
        self::assertSame($event->aggregateId(), $row['aggregate_id']);
        // Postgres returns JSONB as a string via DBAL fetchAssociative.
        $payload = is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'];
        self::assertIsArray($payload);
        self::assertArrayHasKey('card_id', $payload);
    }
}
