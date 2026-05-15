<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Merchant;

use App\Domain\Merchant\Exception\InvalidMerchantCategoryCodeException;
use App\Domain\Merchant\MerchantCategoryCode;
use PHPUnit\Framework\TestCase;

final class MerchantCategoryCodeTest extends TestCase
{
    public function test_a_valid_four_digit_code_is_accepted(): void
    {
        $mcc = new MerchantCategoryCode('5812', 'Eating Places and Restaurants');

        self::assertSame('5812', $mcc->code);
        self::assertSame('Eating Places and Restaurants', $mcc->description);
    }

    /**
     * @dataProvider invalidCodes
     */
    public function test_an_invalid_code_format_is_rejected(string $invalidCode): void
    {
        $this->expectException(InvalidMerchantCategoryCodeException::class);

        new MerchantCategoryCode($invalidCode, 'whatever');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'too short' => ['123'];
        yield 'too long' => ['12345'];
        yield 'contains letters' => ['12ab'];
        yield 'empty' => [''];
        yield 'whitespace' => ['1234 '];
    }

    public function test_named_constructors_match_their_documented_codes(): void
    {
        self::assertSame('5541', MerchantCategoryCode::gasStations()->code);
        self::assertSame('4121', MerchantCategoryCode::rideSharing()->code);
        self::assertSame('5812', MerchantCategoryCode::restaurants()->code);
        self::assertSame('5411', MerchantCategoryCode::groceryStores()->code);
    }

    public function test_equality_is_by_code_alone(): void
    {
        $a = new MerchantCategoryCode('5812', 'Eating Places');
        $b = new MerchantCategoryCode('5812', 'A different description');

        self::assertTrue($a->equals($b));
    }

    public function test_different_codes_are_not_equal(): void
    {
        self::assertFalse(
            MerchantCategoryCode::gasStations()->equals(MerchantCategoryCode::restaurants()),
        );
    }
}
