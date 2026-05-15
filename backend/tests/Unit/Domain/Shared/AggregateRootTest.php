<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\AbstractDomainEvent;
use App\Domain\Shared\AggregateRoot;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function test_release_events_returns_raised_events_in_order(): void
    {
        $aggregate = new class extends AggregateRoot {
            public function doSomething(string $tag): void
            {
                $this->raise(new class(new \DateTimeImmutable(), $tag) extends AbstractDomainEvent {
                    public function __construct(\DateTimeImmutable $occurredAt, public readonly string $tag)
                    {
                        parent::__construct($occurredAt);
                    }

                    public function aggregateId(): string
                    {
                        return 'test';
                    }

                    public function aggregateType(): string
                    {
                        return 'Test';
                    }

                    public function toArray(): array
                    {
                        return ['tag' => $this->tag];
                    }
                });
            }
        };

        $aggregate->doSomething('first');
        $aggregate->doSomething('second');
        $events = $aggregate->releaseEvents();

        self::assertCount(2, $events);
        self::assertSame(['tag' => 'first'], $events[0]->toArray());
        self::assertSame(['tag' => 'second'], $events[1]->toArray());
    }

    public function test_release_events_drains_the_buffer(): void
    {
        // Releasing twice returns events the first time and nothing the second
        // time: events must be dispatched exactly once.
        $aggregate = new class extends AggregateRoot {
            public function doSomething(): void
            {
                $this->raise(new class(new \DateTimeImmutable()) extends AbstractDomainEvent {
                    public function aggregateId(): string
                    {
                        return 'test';
                    }

                    public function aggregateType(): string
                    {
                        return 'Test';
                    }

                    public function toArray(): array
                    {
                        return [];
                    }
                });
            }
        };

        $aggregate->doSomething();
        self::assertCount(1, $aggregate->releaseEvents());
        self::assertCount(0, $aggregate->releaseEvents());
    }
}
