<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Application\Card\AuthorizeCardCommand;
use App\Application\Card\MerchantLocationData;
use Symfony\Component\HttpFoundation\Request;

final class AuthorizationWebhookRequestParser
{
    public function parse(Request $request): AuthorizeCardCommand
    {
        $body = JsonReader::decode($request);
        $merchant = JsonReader::object($body, 'merchant');

        $location = null;
        if (isset($merchant['location']) && is_array($merchant['location'])) {
            /** @var array<string, mixed> $locationData */
            $locationData = $merchant['location'];
            $location = new MerchantLocationData(
                JsonReader::string($locationData, 'city'),
                JsonReader::string($locationData, 'region'),
                JsonReader::string($locationData, 'country'),
            );
        }

        return new AuthorizeCardCommand(
            processorAuthId: JsonReader::string($body, 'processor_auth_id'),
            cardId: JsonReader::string($body, 'card_id'),
            amount: JsonReader::int($body, 'amount'),
            currency: JsonReader::string($body, 'currency'),
            merchantName: JsonReader::string($merchant, 'name'),
            merchantCategoryCode: JsonReader::string($merchant, 'category_code'),
            merchantLocation: $location,
            requestedAt: JsonReader::dateTime($body, 'requested_at'),
        );
    }
}
