<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Application\Idempotency\IdempotencyStore;

/**
 * Redis-backed implementation of the idempotency cache. Keys are namespaced
 * so other tenants of the same Redis instance can't collide.
 *
 * Falls back gracefully on the application-level uniqueness constraint if
 * the cache is unavailable (the cache is the fast path, not the source of
 * truth — see the AuthorizationRepository unique index).
 */
final class RedisIdempotencyStore implements IdempotencyStore
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $keyPrefix = 'sentinel:idem:',
    ) {
    }

    public function retrieve(string $key): ?string
    {
        $value = $this->redis->get($this->keyPrefix.$key);

        // ext-redis returns false on miss; normalise to null for the port.
        if (false === $value) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    public function store(string $key, string $response, int $ttlSeconds = 86_400): void
    {
        $this->redis->setex($this->keyPrefix.$key, $ttlSeconds, $response);
    }
}
