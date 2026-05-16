<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Authorization\Authorization;
use App\Domain\Authorization\AuthorizationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAuthorizationRepository implements AuthorizationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByProcessorAuthId(string $processorAuthId): ?Authorization
    {
        return $this->entityManager
            ->getRepository(Authorization::class)
            ->findOneBy(['processorAuthId' => $processorAuthId]);
    }

    public function save(Authorization $authorization): void
    {
        $this->entityManager->persist($authorization);
    }
}
