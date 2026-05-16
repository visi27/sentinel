<?php

declare(strict_types=1);

namespace App\Application\Card;

/**
 * Plain transport for merchant location on inbound commands. Stays in the
 * application layer because the domain's GeoLocation is built from this in
 * the handler — request shape and domain shape evolve independently.
 */
final class MerchantLocationData
{
    public function __construct(
        public readonly string $city,
        public readonly string $region,
        public readonly string $country,
    ) {
    }
}
