<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exception\InvalidIdentifierException;

/**
 * Base for UUID-backed identifier value objects. Subclasses are one-liners;
 * the type itself is what carries meaning, so a CardId can never be passed
 * where a CardholderId is expected.
 *
 * @phpstan-consistent-constructor
 */
abstract class Identifier
{
    // Protected (not private) so PHPStan can enforce the consistent-constructor
    // invariant on subclasses; functionally equivalent since every subclass is
    // final and declares no constructor of its own.
    protected function __construct(private readonly string $value)
    {
    }

    public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    public static function fromString(string $value): static
    {
        if (!Uuid::isValid($value)) {
            throw InvalidIdentifierException::forValue($value, static::class);
        }

        return new static($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }
}
