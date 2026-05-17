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
        $shortName = false !== ($pos = strrpos($identifierType, '\\'))
            ? substr($identifierType, $pos + 1)
            : $identifierType;

        return new self(sprintf(
            '"%s" is not a valid %s: expected a UUID string.',
            $value,
            $shortName,
        ));
    }
}
