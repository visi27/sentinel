<?php

declare(strict_types=1);

namespace App\Application\Outbox;

/**
 * Worker-side port for draining the outbox. Kept separate from
 * OutboxRepository (which command handlers use to STORE events) so
 * application code stays minimally aware of the publishing pipeline.
 *
 * Implementations must use SELECT ... FOR UPDATE SKIP LOCKED so multiple
 * workers can drain concurrently without conflict.
 */
interface OutboxReader
{
    /**
     * @return list<OutboxRecord>
     */
    public function fetchUnpublishedBatch(int $batchSize = 100): array;

    public function markPublished(string $eventId): void;

    public function markFailed(string $eventId, string $error): void;
}
