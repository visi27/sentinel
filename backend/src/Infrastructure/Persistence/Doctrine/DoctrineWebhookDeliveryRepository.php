<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Webhook\WebhookDelivery;
use App\Application\Webhook\WebhookDeliveryRepository;
use Doctrine\DBAL\Connection;

final class DoctrineWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    private const STATUS_DISPATCHED = 'dispatched';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function recordDispatch(WebhookDelivery $delivery): void
    {
        $this->connection->insert('webhook_deliveries', [
            'id' => $delivery->id,
            'subscriber_id' => $delivery->subscriberId,
            'event_id' => $delivery->eventId,
            'event_type' => $delivery->eventType,
            'url' => $delivery->url,
            'status' => self::STATUS_DISPATCHED,
            'attempt_count' => 0,
            'created_at' => $delivery->createdAt->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
