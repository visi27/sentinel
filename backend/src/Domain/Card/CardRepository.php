<?php

declare(strict_types=1);

namespace App\Domain\Card;

/**
 * Persistence port for the Card aggregate. Implementations live in the
 * infrastructure layer; the domain owns the contract so application services
 * never depend on Doctrine directly.
 *
 * Implementations do not flush — the transaction manager owns the unit of
 * work boundary.
 */
interface CardRepository
{
    public function findById(CardId $id): ?Card;

    public function save(Card $card): void;
}
