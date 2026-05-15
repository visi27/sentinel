<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Shared\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_v7_produces_a_canonically_formatted_uuid(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            Uuid::v7(),
        );
    }

    public function test_v7_sets_the_version_nibble_to_seven(): void
    {
        // Version nibble is the first character of the third hyphen-delimited
        // group; for v7 it must always be "7".
        $uuid = Uuid::v7();

        self::assertSame('7', $uuid[14]);
    }

    public function test_v7_sets_the_rfc_4122_variant_bits(): void
    {
        // Variant byte's top two bits must be 10, so the first nibble of the
        // fourth group is one of 8, 9, a, or b.
        $uuid = Uuid::v7();

        self::assertContains($uuid[19], ['8', '9', 'a', 'b']);
    }

    public function test_v7_is_time_ordered_across_successive_calls(): void
    {
        // Burn through enough calls that the timestamp ticks at least once.
        $first = Uuid::v7();
        usleep(2_000);
        $second = Uuid::v7();

        // The leading 48 bits encode unix time in ms, so comparing the first
        // 12 hex chars (ignoring the hyphen) reflects chronological order.
        $firstTs = substr($first, 0, 8).substr($first, 9, 4);
        $secondTs = substr($second, 0, 8).substr($second, 9, 4);

        self::assertLessThanOrEqual(0, strcmp($firstTs, $secondTs));
    }

    public function test_two_uuids_are_distinct(): void
    {
        self::assertNotSame(Uuid::v7(), Uuid::v7());
    }

    public function test_is_valid_accepts_well_formed_uuids(): void
    {
        self::assertTrue(Uuid::isValid('018e7c8a-1d2b-7d3e-9abc-def012345678'));
        self::assertTrue(Uuid::isValid(strtoupper('018e7c8a-1d2b-7d3e-9abc-def012345678')));
    }

    public function test_is_valid_rejects_malformed_strings(): void
    {
        self::assertFalse(Uuid::isValid(''));
        self::assertFalse(Uuid::isValid('not-a-uuid'));
        self::assertFalse(Uuid::isValid('018e7c8a1d2b7d3e9abcdef012345678'));
        self::assertFalse(Uuid::isValid('zzzzzzzz-1d2b-7d3e-9abc-def012345678'));
    }
}
