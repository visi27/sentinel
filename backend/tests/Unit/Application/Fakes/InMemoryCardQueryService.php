<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Card\CardQueryService;
use App\Application\Card\CardView;
use App\Domain\Card\CardId;

final class InMemoryCardQueryService implements CardQueryService
{
    /** @var array<string, CardView> */
    private array $views = [];

    public function add(CardView $view): void
    {
        $this->views[$view->id] = $view;
    }

    public function findCardView(CardId $id): ?CardView
    {
        return $this->views[$id->toString()] ?? null;
    }
}
