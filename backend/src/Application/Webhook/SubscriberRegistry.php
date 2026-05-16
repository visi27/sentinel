<?php

declare(strict_types=1);

namespace App\Application\Webhook;

/**
 * The fan-out lookup the outbox worker uses to find every subscriber that
 * should receive a given event. Built once at boot from container
 * parameters — see config/packages/subscribers.yaml.
 */
final class SubscriberRegistry
{
    /** @var list<Subscriber> */
    private readonly array $subscribers;

    /**
     * @param list<array{id: string, name: string, url: string, secret: string, event_types: list<string>, active: bool}> $subscribers
     */
    public function __construct(array $subscribers)
    {
        $this->subscribers = array_map(
            static fn (array $row): Subscriber => new Subscriber(
                id: $row['id'],
                name: $row['name'],
                url: $row['url'],
                secret: $row['secret'],
                eventTypes: $row['event_types'],
                active: $row['active'],
            ),
            $subscribers,
        );
    }

    /**
     * @return list<Subscriber>
     */
    public function listenersFor(string $eventType): array
    {
        return array_values(array_filter(
            $this->subscribers,
            static fn (Subscriber $subscriber): bool => $subscriber->listensFor($eventType),
        ));
    }
}
