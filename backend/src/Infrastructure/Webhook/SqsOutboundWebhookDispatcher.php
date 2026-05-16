<?php

declare(strict_types=1);

namespace App\Infrastructure\Webhook;

use App\Application\Webhook\OutboundWebhookDispatcher;
use App\Application\Webhook\WebhookDelivery;
use AsyncAws\Sqs\SqsClient;

/**
 * Sends each WebhookDelivery as an SQS message. The Lambda function in
 * lambda/ is event-source-mapped to this queue.
 */
final class SqsOutboundWebhookDispatcher implements OutboundWebhookDispatcher
{
    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly string $queueUrl,
    ) {
    }

    public function dispatch(WebhookDelivery $delivery): void
    {
        $body = json_encode([
            'delivery_id' => $delivery->id,
            'subscriber_id' => $delivery->subscriberId,
            'event_id' => $delivery->eventId,
            'event_type' => $delivery->eventType,
            'url' => $delivery->url,
            'payload' => $delivery->payload,
            'signature_header' => $delivery->signatureHeader,
        ], JSON_THROW_ON_ERROR);

        $this->sqsClient->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $body,
            'MessageAttributes' => [
                'event_type' => [
                    'DataType' => 'String',
                    'StringValue' => $delivery->eventType,
                ],
            ],
        ])->resolve();
    }
}
