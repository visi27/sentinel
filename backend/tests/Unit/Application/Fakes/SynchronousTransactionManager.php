<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Shared\TransactionManager;

/**
 * Runs the work synchronously with no transactional semantics — sufficient
 * for unit tests, which care about the orchestration, not the database.
 */
final class SynchronousTransactionManager implements TransactionManager
{
    public function run(callable $work): mixed
    {
        return $work();
    }
}
