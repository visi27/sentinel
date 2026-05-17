<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Application\Card\IssueCardCommand;
use Symfony\Component\HttpFoundation\Request;

final class IssueCardRequestParser
{
    /** API-supported settlement currencies. The domain knows more (USD/EUR/GBP)
     *  but the inbound contract is USD-only today; widen this list when adding
     *  multi-currency support to the rest of the surface area.
     */
    private const ALLOWED_CURRENCIES = ['USD'];

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
            currency: JsonReader::stringEnum($body, 'currency', self::ALLOWED_CURRENCIES),
            allowedMerchantCategoryCodes: JsonReader::nonEmptyStringList($body, 'allowed_merchant_categories'),
        );
    }
}
