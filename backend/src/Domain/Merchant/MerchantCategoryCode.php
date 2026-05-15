<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Merchant\Exception\InvalidMerchantCategoryCodeException;

/**
 * A 4-digit ISO 18245 merchant category code. The description is a human
 * label only; equality is by code.
 */
final class MerchantCategoryCode
{
    public function __construct(
        public readonly string $code,
        public readonly string $description,
    ) {
        if (1 !== preg_match('/^\d{4}$/', $code)) {
            throw InvalidMerchantCategoryCodeException::forCode($code);
        }
    }

    public static function gasStations(): self
    {
        return new self('5541', 'Service Stations');
    }

    public static function rideSharing(): self
    {
        return new self('4121', 'Taxicabs and Limousines');
    }

    public static function restaurants(): self
    {
        return new self('5812', 'Eating Places and Restaurants');
    }

    public static function groceryStores(): self
    {
        return new self('5411', 'Grocery Stores and Supermarkets');
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
