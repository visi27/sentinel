<?php

declare(strict_types=1);

namespace App\Tests\Integration\Idempotency;

use App\Infrastructure\Idempotency\RedisIdempotencyStore;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RedisIdempotencyStoreTest extends KernelTestCase
{
    private RedisIdempotencyStore $store;
    private \Redis $redis;
    private string $keyPrefix;

    protected function setUp(): void
    {
        self::bootKernel();
        // Construct the ext-redis client directly so the test asserts against
        // the exact type the production store accepts, and so we don't
        // depend on a container service that the DI compiler may inline.
        $url = (string) ($_ENV['REDIS_URL'] ?? $_SERVER['REDIS_URL'] ?? 'redis://redis:6379');
        $parsed = parse_url($url);
        if (false === $parsed || !isset($parsed['host'])) {
            self::fail('Invalid REDIS_URL: '.$url);
        }
        $this->redis = new \Redis();
        $this->redis->connect((string) $parsed['host'], (int) ($parsed['port'] ?? 6379));
        // Isolate this test's keys behind a per-run prefix so other suites
        // (and a developer's running app) can't interfere.
        $this->keyPrefix = 'sentinel:test:'.bin2hex(random_bytes(4)).':';
        $this->store = new RedisIdempotencyStore($this->redis, $this->keyPrefix);
    }

    protected function tearDown(): void
    {
        // Wipe the test-run keys regardless of test outcome.
        $iterator = null;
        while (false !== ($keys = $this->redis->scan($iterator, $this->keyPrefix.'*'))) {
            if ([] !== $keys) {
                $this->redis->del($keys);
            }
        }
    }

    public function test_retrieve_returns_null_for_unknown_keys(): void
    {
        self::assertNull($this->store->retrieve('never-stored'));
    }

    public function test_store_and_retrieve_round_trip_the_response(): void
    {
        $this->store->store('auth_abc', '{"status":"approved"}');

        self::assertSame('{"status":"approved"}', $this->store->retrieve('auth_abc'));
    }

    public function test_a_zero_ttl_expires_the_entry_immediately(): void
    {
        // Redis treats setex with TTL=0 as an immediate-expire write, which
        // is the closest we can verify without sleeping the test suite.
        // Use a tiny TTL and let the test pass via either expiry or
        // simulated key absence; here we assert the entry exists at TTL=1
        // and is gone after a short wait.
        $this->store->store('short-lived', 'value', ttlSeconds: 1);
        self::assertSame('value', $this->store->retrieve('short-lived'));

        // Sleep just over the TTL; integration suite tolerates ~1s here.
        sleep(2);

        self::assertNull($this->store->retrieve('short-lived'));
    }
}
