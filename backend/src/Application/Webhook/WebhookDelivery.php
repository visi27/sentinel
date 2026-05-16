<?php

declare(strict_types=1);

namespace App\Application\Webhook;

use App\Application\Outbox\OutboxRecord;
use App\Domain\Shared\Uuid;

/**
 * The unit of work the SQS queue carries. Pre-built by the outbox worker
 * (subscriber URL resolved, payload serialized, signature computed) so the
 * Lambda handler has only to POST.
 */
final class WebhookDelivery
{
    public function __construct(
        public readonly string $id,
        public readonly string $subscriberId,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $url,
        public readonly string $payload,
        public readonly string $signatureHeader,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function forEvent(
        Subscriber $subscriber,
        OutboxRecord $record,
        \DateTimeImmutable $now,
    ): self {
        $payload = json_encode([
            'event_id' => $record->eventId,
            'event_type' => $record->eventType,
            'occurred_at' => $record->occurredAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'data' => $record->payload,
        ], JSON_THROW_ON_ERROR);

        $timestamp = $now->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $subscriber->secret);

        return new self(
            id: Uuid::v7(),
            subscriberId: $subscriber->id,
            eventId: $record->eventId,
            eventType: $record->eventType,
            url: $subscriber->url,
            payload: $payload,
            signatureHeader: sprintf('t=%d,v1=%s', $timestamp, $signature),
            createdAt: $now,
        );
    }
}
