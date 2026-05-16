<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Http\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Small helper for parsing a JSON request body field-by-field with type
 * validation. Keeps request parsers free of repetitive isset/is_string
 * boilerplate.
 */
final class JsonReader
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            throw InvalidRequestException::malformedJson();
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidRequestException::malformedJson();
        }

        if (!is_array($decoded)) {
            throw InvalidRequestException::malformedJson();
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function string(array $body, string $field): string
    {
        if (!array_key_exists($field, $body)) {
            throw InvalidRequestException::missingField($field);
        }
        $value = $body[$field];
        if (!is_string($value) || '' === $value) {
            throw InvalidRequestException::wrongType($field, 'non-empty string');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function int(array $body, string $field): int
    {
        if (!array_key_exists($field, $body)) {
            throw InvalidRequestException::missingField($field);
        }
        $value = $body[$field];
        if (!is_int($value)) {
            throw InvalidRequestException::wrongType($field, 'integer');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function object(array $body, string $field): array
    {
        if (!array_key_exists($field, $body)) {
            throw InvalidRequestException::missingField($field);
        }
        $value = $body[$field];
        if (!is_array($value)) {
            throw InvalidRequestException::wrongType($field, 'object');
        }

        /* @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    public static function stringList(array $body, string $field): array
    {
        if (!array_key_exists($field, $body)) {
            throw InvalidRequestException::missingField($field);
        }
        $value = $body[$field];
        if (!is_array($value)) {
            throw InvalidRequestException::wrongType($field, 'array of strings');
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw InvalidRequestException::wrongType($field, 'array of strings');
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function dateTime(array $body, string $field): \DateTimeImmutable
    {
        $raw = self::string($body, $field);
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw InvalidRequestException::invalidValue($field, 'expected an ISO 8601 timestamp');
        }
    }
}
