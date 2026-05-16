<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\CardId;

/**
 * Read-side port. Implementations bypass the Card aggregate and project
 * directly from the read store, so the query path does not pay for
 * aggregate hydration.
 */
interface CardQueryService
{
    public function findCardView(CardId $id): ?CardView;
}
