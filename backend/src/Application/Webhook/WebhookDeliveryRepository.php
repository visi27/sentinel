<?php

declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * Persists the dispatch record for an outbound delivery. Status changes
 * after dispatch (delivered, failed, dead-lettered) would be written by
 * the Lambda or a feedback consumer — out of scope for this sample.
 */
interface WebhookDeliveryRepository
{
    public function recordDispatch(WebhookDelivery $delivery): void;
}
