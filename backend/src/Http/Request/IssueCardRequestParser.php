<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Application\Card\IssueCardCommand;
use Symfony\Component\HttpFoundation\Request;

final class IssueCardRequestParser
{
    public function parse(Request $request): IssueCardCommand
    {
        $body = JsonReader::decode($request);
        $limits = JsonReader::object($body, 'spending_limits');

        return new IssueCardCommand(
            cardholderId: JsonReader::string($body, 'cardholder_id'),
            perTransactionLimit: JsonReader::int($limits, 'per_transaction'),
            dailyLimit: JsonReader::int($limits, 'daily'),
            monthlyLimit: JsonReader::int($limits, 'monthly'),
            initialBalance: JsonReader::int($body, 'initial_balance'),
            currency: JsonReader::string($body, 'currency'),
            allowedMerchantCategoryCodes: JsonReader::stringList($body, 'allowed_merchant_categories'),
        );
    }
}
