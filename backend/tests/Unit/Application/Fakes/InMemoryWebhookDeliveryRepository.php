<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Webhook\WebhookDelivery;
use App\Application\Webhook\WebhookDeliveryRepository;

final class InMemoryWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    /** @var list<WebhookDelivery> */
    public array $recorded = [];

    public function recordDispatch(WebhookDelivery $delivery): void
    {
        $this->recorded[] = $delivery;
    }
}
