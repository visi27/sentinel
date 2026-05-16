<?php

declare(strict_types=1);

namespace App\Domain\Card\Exception;

use App\Domain\Card\CardId;
use App\Domain\Shared\Exception\DomainException;

/**
 * Raised when an administrative command references a card that does not
 * exist. The authorization flow does NOT throw this — it records a declined
 * Authorization for audit instead.
 */
final class CardNotFoundException extends DomainException
{
    public static function withId(CardId $id): self
    {
        return new self(sprintf('Card %s does not exist.', $id->toString()));
    }
}
