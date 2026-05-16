<?php

declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Hands a delivery off to the queue. Implementations live in
 * infrastructure (SQS today, easy to swap for an alternative transport
 * later) — the worker only knows the port.
 */
interface OutboundWebhookDispatcher
{
    public function dispatch(WebhookDelivery $delivery): void;
}
