<?php

declare(strict_types=1);

namespace App\Application\Card;

final class GetCardQuery
{
    public function __construct(
        public readonly string $cardId,
    ) {
    }
}
