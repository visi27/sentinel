<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Card\CardQueryService;
use App\Application\Card\CardView;
use App\Application\Card\MoneyView;
use App\Application\Card\SpendingLimitsView;
use App\Domain\Card\CardId;
use Doctrine\DBAL\Connection;

/**
 * Reads Card rows directly via DBAL so the query path skips the cost of
 * aggregate hydration. Returns the same CardView shape the controller
 * eventually serializes.
 */
final class DoctrineCardQueryService implements CardQueryService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findCardView(CardId $id): ?CardView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM cards WHERE id = :id',
            ['id' => $id->toString()],
        );

        if (false === $row) {
            return null;
        }

        $allowedCodes = is_string($row['allowed_merchant_category_codes'])
            ? json_decode($row['allowed_merchant_category_codes'], true)
            : $row['allowed_merchant_category_codes'];
        $codes = is_array($allowedCodes)
            ? array_values(array_map(static fn ($code): string => (string) $code, $allowedCodes))
            : [];

        return new CardView(
            id: (string) $row['id'],
            cardholderId: (string) $row['cardholder_id'],
            status: (string) $row['status'],
            spendingLimits: new SpendingLimitsView(
                perTransaction: new MoneyView(
                    (int) $row['spending_limits_per_transaction_minor_units'],
                    (string) $row['spending_limits_per_transaction_currency'],
                ),
                daily: new MoneyView(
                    (int) $row['spending_limits_daily_minor_units'],
                    (string) $row['spending_limits_daily_currency'],
                ),
                monthly: new MoneyView(
                    (int) $row['spending_limits_monthly_minor_units'],
                    (string) $row['spending_limits_monthly_currency'],
                ),
            ),
            availableBalance: new MoneyView(
                (int) $row['available_balance_minor_units'],
                (string) $row['available_balance_currency'],
            ),
            dailySpend: new MoneyView(
                (int) $row['daily_spend_minor_units'],
                (string) $row['daily_spend_currency'],
            ),
            monthlySpend: new MoneyView(
                (int) $row['monthly_spend_minor_units'],
                (string) $row['monthly_spend_currency'],
            ),
            allowedMerchantCategories: $codes,
            issuedAt: new \DateTimeImmutable((string) $row['issued_at']),
            activatedAt: null === $row['activated_at'] ? null : new \DateTimeImmutable((string) $row['activated_at']),
            closedAt: null === $row['closed_at'] ? null : new \DateTimeImmutable((string) $row['closed_at']),
        );
    }
}
