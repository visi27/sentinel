<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\CardView;
use App\Application\Card\GetCardQuery;
use App\Application\Card\GetCardQueryHandler;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Tests\Unit\Application\Fakes\InMemoryCardQueryService;
use PHPUnit\Framework\TestCase;

final class GetCardQueryHandlerTest extends TestCase
{
    public function test_returns_a_card_view_when_the_card_exists(): void
    {
        $service = new InMemoryCardQueryService();
        $card = Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(50_00), Money::usd(100_00), Money::usd(500_00)),
            Money::usd(1_000_00),
            [MerchantCategoryCode::rideSharing()],
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );
        $view = CardView::fromCard($card);
        $service->add($view);

        $result = (new GetCardQueryHandler($service))(new GetCardQuery($card->id()->toString()));

        self::assertSame($view, $result);
    }

    public function test_returns_null_when_the_card_does_not_exist(): void
    {
        $handler = new GetCardQueryHandler(new InMemoryCardQueryService());

        $result = $handler(new GetCardQuery('018e7c8a-1d2b-7d3e-9abc-def012345678'));

        self::assertNull($result);
    }
}
