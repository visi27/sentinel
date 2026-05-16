<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Card\CardId;

final class ListAuthorizationsForCardQueryHandler
{
    public function __construct(
        private readonly AuthorizationQueryService $authorizationQueryService,
    ) {
    }

    public function __invoke(ListAuthorizationsForCardQuery $query): AuthorizationListView
    {
        return $this->authorizationQueryService->listForCard(
            CardId::fromString($query->cardId),
            $query->page,
            $query->perPage,
        );
    }
}
