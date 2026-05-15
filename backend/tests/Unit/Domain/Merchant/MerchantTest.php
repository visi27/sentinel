<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Merchant;

use App\Domain\Merchant\GeoLocation;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use PHPUnit\Framework\TestCase;

final class MerchantTest extends TestCase
{
    public function test_a_merchant_can_be_constructed_without_a_location(): void
    {
        $merchant = new Merchant('Uber', MerchantCategoryCode::rideSharing());

        self::assertSame('Uber', $merchant->name);
        self::assertNull($merchant->location);
    }

    public function test_equality_compares_name_category_and_location(): void
    {
        $loc = new GeoLocation('Boston', 'MA', 'US');
        $a = new Merchant('Uber', MerchantCategoryCode::rideSharing(), $loc);
        $b = new Merchant('Uber', MerchantCategoryCode::rideSharing(), new GeoLocation('Boston', 'MA', 'US'));

        self::assertTrue($a->equals($b));
    }

    public function test_a_different_name_breaks_equality(): void
    {
        $a = new Merchant('Uber', MerchantCategoryCode::rideSharing());
        $b = new Merchant('Lyft', MerchantCategoryCode::rideSharing());

        self::assertFalse($a->equals($b));
    }

    public function test_a_present_location_is_not_equal_to_a_missing_one(): void
    {
        $a = new Merchant('Uber', MerchantCategoryCode::rideSharing(), new GeoLocation('Boston', 'MA', 'US'));
        $b = new Merchant('Uber', MerchantCategoryCode::rideSharing());

        self::assertFalse($a->equals($b));
    }
}
