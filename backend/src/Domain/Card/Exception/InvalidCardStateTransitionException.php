<?php

declare(strict_types=1);

namespace App\Domain\Card\Exception;

use App\Domain\Card\CardStatus;
use App\Domain\Shared\Exception\DomainException;

final class InvalidCardStateTransitionException extends DomainException
{
    public static function from(CardStatus $current, CardStatus $target): self
    {
        return new self(sprintf(
            'A card cannot move from %s to %s.',
            $current->value,
            $target->value,
        ));
    }

    public static function cannotChangeLimitsWhenClosed(): self
    {
        return new self('Spending limits cannot be changed on a closed card.');
    }
}
