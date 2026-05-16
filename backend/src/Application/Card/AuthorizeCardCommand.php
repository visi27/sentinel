<?php

declare(strict_types=1);

namespace App\Application\Card;

/**
 * The shape of an inbound authorization request after it has been parsed and
 * validated by the HTTP layer. Pure data — no behaviour.
 */
final class AuthorizeCardCommand
{
    public function __construct(
        public readonly string $processorAuthId,
        public readonly string $cardId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $merchantName,
        public readonly string $merchantCategoryCode,
        public readonly ?MerchantLocationData $merchantLocation,
        public readonly \DateTimeImmutable $requestedAt,
    ) {
    }
}
