<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Pure-PHP UUID v7 generation, kept in the domain so identifier value objects
 * have no framework dependency. UUID v7 is time-ordered (RFC 9562), which keeps
 * primary keys index-friendly without leaking sequential counters.
 */
final class Uuid
{
    private const FORMAT = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private function __construct()
    {
    }

    public static function v7(): string
    {
        $unixTsMs = (int) (microtime(true) * 1000);

        $bytes = random_bytes(16);

        // Bytes 0-5: 48-bit big-endian millisecond timestamp.
        $bytes[0] = chr(($unixTsMs >> 40) & 0xFF);
        $bytes[1] = chr(($unixTsMs >> 32) & 0xFF);
        $bytes[2] = chr(($unixTsMs >> 24) & 0xFF);
        $bytes[3] = chr(($unixTsMs >> 16) & 0xFF);
        $bytes[4] = chr(($unixTsMs >> 8) & 0xFF);
        $bytes[5] = chr($unixTsMs & 0xFF);

        // Byte 6 high nibble: version 7. Byte 8 high bits: RFC 4122 variant (10).
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    public static function isValid(string $value): bool
    {
        return 1 === preg_match(self::FORMAT, $value);
    }
}
