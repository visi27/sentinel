<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class InvalidIdentifierException extends DomainException
{
    /**
     * @param class-string $identifierType
     */
    public static function forValue(string $value, string $identifierType): self
    {
        return new self(sprintf(
            '"%s" is not a valid %s: expected a UUID string.',
            $value,
            $identifierType,
        ));
    }
}
