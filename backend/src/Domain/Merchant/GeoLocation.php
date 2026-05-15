<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

/**
 * Where a transaction took place. Optional on a Merchant — the processor does
 * not always supply it.
 */
final class GeoLocation
{
    public function __construct(
        public readonly string $city,
        public readonly string $region,
        public readonly string $country,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->city === $other->city
            && $this->region === $other->region
            && $this->country === $other->country;
    }
}
