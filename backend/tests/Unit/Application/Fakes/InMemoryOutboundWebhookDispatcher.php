<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Webhook\OutboundWebhookDispatcher;
use App\Application\Webhook\WebhookDelivery;

final class InMemoryOutboundWebhookDispatcher implements OutboundWebhookDispatcher
{
    /** @var list<WebhookDelivery> */
    public array $dispatched = [];

    public function dispatch(WebhookDelivery $delivery): void
    {
        $this->dispatched[] = $delivery;
    }
}
