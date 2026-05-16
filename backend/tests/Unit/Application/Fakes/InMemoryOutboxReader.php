<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Outbox\OutboxReader;
use App\Application\Outbox\OutboxRecord;

final class InMemoryOutboxReader implements OutboxReader
{
    /** @var array<string, OutboxRecord> */
    private array $records = [];

    /** @var array<string, true> */
    public array $published = [];

    /** @var array<string, string> */
    public array $failed = [];

    public function seed(OutboxRecord $record): void
    {
        $this->records[$record->eventId] = $record;
    }

    public function fetchUnpublishedBatch(int $batchSize = 100): array
    {
        $remaining = array_values(array_filter(
            $this->records,
            fn (OutboxRecord $r): bool => !isset($this->published[$r->eventId])
                && !isset($this->failed[$r->eventId]),
        ));

        return array_slice($remaining, 0, $batchSize);
    }

    public function markPublished(string $eventId): void
    {
        $this->published[$eventId] = true;
    }

    public function markFailed(string $eventId, string $error): void
    {
        $this->failed[$eventId] = $error;
    }
}
