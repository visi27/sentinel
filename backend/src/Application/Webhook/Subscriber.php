<?php

declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * A downstream consumer of domain events. Immutable, resolved at startup
 * from the container-managed subscriber list (config/packages/subscribers.yaml).
 */
final class Subscriber
{
    /**
     * @param list<string> $eventTypes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        #[\SensitiveParameter] public readonly string $secret,
        public readonly array $eventTypes,
        public readonly bool $active,
    ) {
    }

    public function listensFor(string $eventType): bool
    {
        return $this->active && in_array($eventType, $this->eventTypes, true);
    }
}
