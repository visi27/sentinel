<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Outbox;

use App\Application\Outbox\OutboxRecord;
use App\Application\Webhook\SubscriberRegistry;
use App\Domain\Shared\Uuid;
use App\Infrastructure\Outbox\OutboxPublishCommand;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryOutboundWebhookDispatcher;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxReader;
use App\Tests\Unit\Application\Fakes\InMemoryWebhookDeliveryRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class OutboxPublishCommandTest extends TestCase
{
    public function test_a_card_authorization_event_fans_out_to_every_listening_subscriber(): void
    {
        $outbox = new InMemoryOutboxReader();
        $dispatcher = new InMemoryOutboundWebhookDispatcher();
        $deliveries = new InMemoryWebhookDeliveryRepository();

        $eventId = Uuid::v7();
        $authorizationId = Uuid::v7();
        $cardId = Uuid::v7();
        $outbox->seed(new OutboxRecord(
            eventId: $eventId,
            eventType: 'card.authorization.approved',
            aggregateId: $authorizationId,
            aggregateType: 'Authorization',
            payload: ['card_id' => $cardId, 'authorization_id' => $authorizationId],
            occurredAt: new \DateTimeImmutable('2026-05-14T12:34:57Z'),
        ));

        $registry = new SubscriberRegistry([
            [
                'id' => 'analytics',
                'name' => 'Analytics',
                'url' => 'https://example.test/analytics',
                'secret' => 'analytics_secret',
                'event_types' => ['card.authorization.approved', 'card.authorization.declined'],
                'active' => true,
            ],
            [
                'id' => 'sponsor',
                'name' => 'Sponsor',
                'url' => 'https://example.test/sponsor',
                'secret' => 'sponsor_secret',
                // Doesn't listen for this event — must NOT receive a dispatch.
                'event_types' => ['card.issued'],
                'active' => true,
            ],
        ]);

        $command = new OutboxPublishCommand(
            outbox: $outbox,
            subscribers: $registry,
            dispatcher: $dispatcher,
            deliveryRepository: $deliveries,
            transactionManager: new SynchronousTransactionManager(),
            clock: new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:57Z')),
        );

        (new CommandTester($command))->execute(['--once' => true]);

        self::assertCount(1, $dispatcher->dispatched);
        self::assertSame('analytics', $dispatcher->dispatched[0]->subscriberId);
        self::assertCount(1, $deliveries->recorded);
        self::assertArrayHasKey($eventId, $outbox->published);
        self::assertSame([], $outbox->failed);
    }

    public function test_an_event_with_no_listeners_is_marked_published_with_no_dispatch(): void
    {
        $outbox = new InMemoryOutboxReader();
        $dispatcher = new InMemoryOutboundWebhookDispatcher();
        $deliveries = new InMemoryWebhookDeliveryRepository();

        $eventId = Uuid::v7();
        $outbox->seed(new OutboxRecord(
            eventId: $eventId,
            eventType: 'card.spending_limits_changed',
            aggregateId: Uuid::v7(),
            aggregateType: 'Card',
            payload: [],
            occurredAt: new \DateTimeImmutable('2026-05-14T12:34:57Z'),
        ));

        $command = new OutboxPublishCommand(
            outbox: $outbox,
            subscribers: new SubscriberRegistry([]),
            dispatcher: $dispatcher,
            deliveryRepository: $deliveries,
            transactionManager: new SynchronousTransactionManager(),
            clock: new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:57Z')),
        );

        (new CommandTester($command))->execute(['--once' => true]);

        self::assertCount(0, $dispatcher->dispatched);
        self::assertArrayHasKey($eventId, $outbox->published);
    }

    public function test_dispatcher_failure_marks_the_event_failed_and_keeps_processing(): void
    {
        $outbox = new InMemoryOutboxReader();
        $failingDispatcher = new class implements \App\Application\Webhook\OutboundWebhookDispatcher {
            public function dispatch(\App\Application\Webhook\WebhookDelivery $delivery): void
            {
                throw new \RuntimeException('SQS unavailable');
            }
        };
        $deliveries = new InMemoryWebhookDeliveryRepository();

        $eventId = Uuid::v7();
        $outbox->seed(new OutboxRecord(
            eventId: $eventId,
            eventType: 'card.authorization.approved',
            aggregateId: Uuid::v7(),
            aggregateType: 'Authorization',
            payload: [],
            occurredAt: new \DateTimeImmutable('2026-05-14T12:34:57Z'),
        ));

        $registry = new SubscriberRegistry([
            [
                'id' => 'analytics',
                'name' => 'Analytics',
                'url' => 'https://example.test/analytics',
                'secret' => 'analytics_secret',
                'event_types' => ['card.authorization.approved'],
                'active' => true,
            ],
        ]);

        $command = new OutboxPublishCommand(
            outbox: $outbox,
            subscribers: $registry,
            dispatcher: $failingDispatcher,
            deliveryRepository: $deliveries,
            transactionManager: new SynchronousTransactionManager(),
            clock: new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:57Z')),
        );

        (new CommandTester($command))->execute(['--once' => true]);

        self::assertArrayNotHasKey($eventId, $outbox->published);
        self::assertArrayHasKey($eventId, $outbox->failed);
        self::assertStringContainsString('SQS unavailable', $outbox->failed[$eventId]);
    }
}
