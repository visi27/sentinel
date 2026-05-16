<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Card\Card;
use App\Domain\Card\CardId;
use App\Domain\Card\CardRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCardRepository implements CardRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findById(CardId $id): ?Card
    {
        return $this->entityManager->find(Card::class, $id);
    }

    public function save(Card $card): void
    {
        // No flush here — the transaction manager owns the unit-of-work
        // boundary so aggregate writes and outbox writes commit together.
        $this->entityManager->persist($card);
    }
}
