<?php

declare(strict_types=1);

namespace App\Domain\Card;

/**
 * The card lifecycle state machine. Closed is terminal.
 */
enum CardStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function isActive(): bool
    {
        return self::Active === $this;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => self::Active === $target || self::Closed === $target,
            self::Active => self::Suspended === $target || self::Closed === $target,
            self::Suspended => self::Active === $target || self::Closed === $target,
            self::Closed => false,
        };
    }
}
