<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Shared\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionManager implements TransactionManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($work) {
            $result = $work();
            // Flush inside the transaction so persist() calls in the closure
            // become SQL before commit. Repositories are deliberately
            // flush-less to keep this the single commit boundary.
            $this->entityManager->flush();

            return $result;
        });
    }
}
