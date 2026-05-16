<?php

declare(strict_types=1);

namespace App\Application\Card;

final class SuspendCardCommand
{
    public function __construct(
        public readonly string $cardId,
        public readonly string $reason,
    ) {
    }
}
