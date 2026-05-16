<?php

declare(strict_types=1);

namespace App\Application\Card;

final class ActivateCardCommand
{
    public function __construct(
        public readonly string $cardId,
    ) {
    }
}
