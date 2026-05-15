<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Authorization\AuthorizationId;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardId;
use App\Domain\Shared\Exception\InvalidIdentifierException;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function test_generate_returns_an_identifier_with_a_valid_uuid(): void
    {
        $id = CardId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->toString(),
        );
    }

    public function test_from_string_round_trips(): void
    {
        $value = '018e7c8a-1d2b-7d3e-9abc-def012345678';

        self::assertSame($value, CardId::fromString($value)->toString());
    }

    public function test_from_string_rejects_an_invalid_uuid(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        CardId::fromString('not-a-uuid');
    }

    public function test_equals_is_true_for_identifiers_of_the_same_type_and_value(): void
    {
        $value = '018e7c8a-1d2b-7d3e-9abc-def012345678';

        self::assertTrue(CardId::fromString($value)->equals(CardId::fromString($value)));
    }

    public function test_equals_is_false_for_different_values(): void
    {
        self::assertFalse(CardId::generate()->equals(CardId::generate()));
    }

    public function test_equals_is_false_across_identifier_types_even_when_the_uuid_matches(): void
    {
        // The whole point of a typed identifier is to prevent confusing a
        // CardId with a CardholderId at compile time AND at runtime.
        $value = '018e7c8a-1d2b-7d3e-9abc-def012345678';
        $cardId = CardId::fromString($value);
        $cardholderId = CardholderId::fromString($value);

        self::assertFalse($cardId->equals($cardholderId));
    }

    public function test_each_identifier_subclass_returns_its_own_concrete_type(): void
    {
        self::assertInstanceOf(CardId::class, CardId::generate());
        self::assertInstanceOf(CardholderId::class, CardholderId::generate());
        self::assertInstanceOf(AuthorizationId::class, AuthorizationId::generate());
    }
}
