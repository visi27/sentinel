<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\CardId;

final class GetCardQueryHandler
{
    public function __construct(
        private readonly CardQueryService $cardQueryService,
    ) {
    }

    public function __invoke(GetCardQuery $query): ?CardView
    {
        return $this->cardQueryService->findCardView(CardId::fromString($query->cardId));
    }
}
