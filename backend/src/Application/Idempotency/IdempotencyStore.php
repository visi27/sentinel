<?php

declare(strict_types=1);

namespace App\Application\Idempotency;

/**
 * Caches the serialized response of an at-most-once command so retries with
 * the same idempotency key short-circuit before reaching the command
 * handler. The aggregate-level uniqueness constraint is the durable
 * backstop; this cache is the fast path.
 */
interface IdempotencyStore
{
    public function retrieve(string $key): ?string;

    public function store(string $key, string $response, int $ttlSeconds = 86_400): void;
}
