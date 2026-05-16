<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Read-only access to the wall clock, injectable so handlers stay
 * deterministic in tests. Application-owned because the domain receives
 * timestamps explicitly via method parameters.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
