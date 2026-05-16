<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Application\Authorization\AuthorizationListView;
use App\Application\Authorization\AuthorizationQueryService;
use App\Application\Authorization\AuthorizationView;
use App\Domain\Card\CardId;

final class InMemoryAuthorizationQueryService implements AuthorizationQueryService
{
    /** @var array<string, list<AuthorizationView>> */
    private array $views = [];

    /**
     * @param list<AuthorizationView> $views
     */
    public function seed(string $cardId, array $views): void
    {
        $this->views[$cardId] = $views;
    }

    public function listForCard(CardId $cardId, int $page, int $perPage): AuthorizationListView
    {
        $items = $this->views[$cardId->toString()] ?? [];
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return new AuthorizationListView($slice, $page, $perPage, count($items));
    }
}
