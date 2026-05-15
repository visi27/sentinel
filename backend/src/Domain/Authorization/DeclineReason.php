<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

/**
 * Why an authorization was declined. Backed values are the wire format used
 * in webhook responses and outbound events.
 */
enum DeclineReason: string
{
    case CardNotActive = 'CARD_NOT_ACTIVE';
    case InsufficientFunds = 'INSUFFICIENT_FUNDS';
    case ExceedsPerTransactionLimit = 'EXCEEDS_PER_TRANSACTION_LIMIT';
    case ExceedsDailyLimit = 'EXCEEDS_DAILY_LIMIT';
    case ExceedsMonthlyLimit = 'EXCEEDS_MONTHLY_LIMIT';
    case MerchantCategoryBlocked = 'MERCHANT_CATEGORY_BLOCKED';
    case DuplicateAuthorization = 'DUPLICATE_AUTHORIZATION';
}
