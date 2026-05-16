<?php

declare(strict_types=1);

namespace App\Application\Authorization;

use App\Domain\Card\CardId;

interface AuthorizationQueryService
{
    public function listForCard(CardId $cardId, int $page, int $perPage): AuthorizationListView;
}
