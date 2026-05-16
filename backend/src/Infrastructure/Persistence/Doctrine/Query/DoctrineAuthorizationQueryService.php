<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Authorization\AuthorizationListView;
use App\Application\Authorization\AuthorizationQueryService;
use App\Application\Authorization\AuthorizationView;
use App\Application\Card\MoneyView;
use App\Domain\Card\CardId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class DoctrineAuthorizationQueryService implements AuthorizationQueryService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function listForCard(CardId $cardId, int $page, int $perPage): AuthorizationListView
    {
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM authorizations WHERE card_id = :card_id',
            ['card_id' => $cardId->toString()],
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM authorizations WHERE card_id = :card_id'
                .' ORDER BY requested_at DESC LIMIT :limit OFFSET :offset',
            [
                'card_id' => $cardId->toString(),
                'limit' => $perPage,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );

        $items = array_map(fn (array $row): AuthorizationView => $this->rowToView($row), $rows);

        return new AuthorizationListView($items, $page, $perPage, $total);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToView(array $row): AuthorizationView
    {
        $merchantData = is_string($row['merchant']) ? json_decode($row['merchant'], true) : $row['merchant'];
        $merchantData = is_array($merchantData) ? $merchantData : [];

        return new AuthorizationView(
            id: (string) $row['id'],
            cardId: (string) $row['card_id'],
            processorAuthId: (string) $row['processor_auth_id'],
            amount: new MoneyView(
                (int) $row['amount_minor_units'],
                (string) $row['amount_currency'],
            ),
            merchantName: (string) ($merchantData['name'] ?? ''),
            merchantCategoryCode: (string) ($merchantData['category_code'] ?? ''),
            status: (string) $row['status'],
            declineReason: null === $row['decline_reason'] ? null : (string) $row['decline_reason'],
            requestedAt: new \DateTimeImmutable((string) $row['requested_at']),
            decidedAt: null === $row['decided_at'] ? null : new \DateTimeImmutable((string) $row['decided_at']),
            reversedAt: null === $row['reversed_at'] ? null : new \DateTimeImmutable((string) $row['reversed_at']),
        );
    }
}
