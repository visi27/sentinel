<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Authorization\DeclineReason;

/**
 * The outcome of Card::authorize() — an approval, or a decline carrying the
 * first rule that was violated.
 */
final class AuthorizationResult
{
    private function __construct(
        public readonly bool $isApproved,
        public readonly ?DeclineReason $declineReason = null,
    ) {
    }

    public static function approved(): self
    {
        return new self(true);
    }

    public static function declined(DeclineReason $reason): self
    {
        return new self(false, $reason);
    }
}
