<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Domain\Card\Card;
use App\Domain\Card\CardId;
use App\Domain\Card\CardRepository;

final class InMemoryCardRepository implements CardRepository
{
    /** @var array<string, Card> */
    private array $cards = [];

    public int $saveCount = 0;

    public function findById(CardId $id): ?Card
    {
        return $this->cards[$id->toString()] ?? null;
    }

    public function save(Card $card): void
    {
        $this->cards[$card->id()->toString()] = $card;
        ++$this->saveCount;
    }
}
