<?php

declare(strict_types=1);

namespace App\Application\Authorization;

final class ListAuthorizationsForCardQuery
{
    public function __construct(
        public readonly string $cardId,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {
    }
}
