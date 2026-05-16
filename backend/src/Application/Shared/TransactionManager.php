<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Wraps the unit-of-work boundary. Handlers pass a closure that performs the
 * aggregate persistence AND outbox writes; the manager guarantees both
 * commit together or both roll back, eliminating the dual-write problem.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
