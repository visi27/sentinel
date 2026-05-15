<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

/**
 * The counterparty of an authorization. Modelled as a value object with no
 * identity of its own: the platform never needs to reference a merchant
 * independently of the transaction it appears on.
 */
final class Merchant
{
    public function __construct(
        public readonly string $name,
        public readonly MerchantCategoryCode $categoryCode,
        public readonly ?GeoLocation $location = null,
    ) {
    }

    public function equals(self $other): bool
    {
        if ($this->name !== $other->name) {
            return false;
        }

        if (!$this->categoryCode->equals($other->categoryCode)) {
            return false;
        }

        if (null === $this->location || null === $other->location) {
            return $this->location === $other->location;
        }

        return $this->location->equals($other->location);
    }
}
