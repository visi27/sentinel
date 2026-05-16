<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Shared\Clock;

/**
 * Production wall-clock implementation. Always returns the current time in
 * UTC so persisted timestamps stay timezone-agnostic.
 */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
