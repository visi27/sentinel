<?php

declare(strict_types=1);

namespace App\Http\Exception;

/**
 * The HTTP layer's "your payload doesn't satisfy the schema" exception.
 * Translated to a 422 by the kernel exception subscriber.
 */
final class InvalidRequestException extends \RuntimeException
{
    public static function missingField(string $field): self
    {
        return new self(sprintf('Missing required field: %s', $field));
    }

    public static function wrongType(string $field, string $expected): self
    {
        return new self(sprintf('Field "%s" must be of type %s.', $field, $expected));
    }

    public static function invalidValue(string $field, string $detail): self
    {
        return new self(sprintf('Field "%s" is invalid: %s', $field, $detail));
    }

    public static function malformedJson(): self
    {
        return new self('Request body is not valid JSON.');
    }
}
