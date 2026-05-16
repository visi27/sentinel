<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Authorization;

use App\Application\Authorization\AuthorizationView;
use App\Application\Authorization\ListAuthorizationsForCardQuery;
use App\Application\Authorization\ListAuthorizationsForCardQueryHandler;
use App\Application\Card\MoneyView;
use App\Domain\Card\CardId;
use App\Tests\Unit\Application\Fakes\InMemoryAuthorizationQueryService;
use PHPUnit\Framework\TestCase;

final class ListAuthorizationsForCardQueryHandlerTest extends TestCase
{
    public function test_returns_the_first_page_with_total_count(): void
    {
        $service = new InMemoryAuthorizationQueryService();
        $cardId = CardId::generate();
        $service->seed($cardId->toString(), [
            $this->view('auth_1'),
            $this->view('auth_2'),
            $this->view('auth_3'),
        ]);

        $list = (new ListAuthorizationsForCardQueryHandler($service))(
            new ListAuthorizationsForCardQuery($cardId->toString(), page: 1, perPage: 2),
        );

        self::assertCount(2, $list->items);
        self::assertSame(3, $list->totalItems);
        self::assertSame(2, $list->totalPages());
    }

    public function test_returns_an_empty_page_when_there_are_no_authorizations(): void
    {
        $service = new InMemoryAuthorizationQueryService();
        $handler = new ListAuthorizationsForCardQueryHandler($service);

        $list = $handler(new ListAuthorizationsForCardQuery(CardId::generate()->toString()));

        self::assertSame(0, $list->totalItems);
        self::assertSame([], $list->items);
        self::assertSame(0, $list->totalPages());
    }

    private function view(string $processorAuthId): AuthorizationView
    {
        return new AuthorizationView(
            id: '018e7c8a-1d2b-7d3e-9abc-def012345678',
            cardId: '018e7c8a-1d2b-7d3e-9abc-aaaaaaaaaaaa',
            processorAuthId: $processorAuthId,
            amount: new MoneyView(1_000, 'USD'),
            merchantName: 'Uber',
            merchantCategoryCode: '4121',
            status: 'approved',
            declineReason: null,
            requestedAt: new \DateTimeImmutable('2026-05-14T12:34:56Z'),
            decidedAt: new \DateTimeImmutable('2026-05-14T12:34:57Z'),
            reversedAt: null,
        );
    }
}
