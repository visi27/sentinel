<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\ChangeSpendingLimitsCommand;
use App\Application\Card\ChangeSpendingLimitsCommandHandler;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\Event\SpendingLimitsChanged;
use App\Domain\Card\Exception\CardNotFoundException;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryCardRepository;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class ChangeSpendingLimitsCommandHandlerTest extends TestCase
{
    public function test_changes_the_limits_and_emits_the_event(): void
    {
        $cards = new InMemoryCardRepository();
        $outbox = new InMemoryOutboxRepository();
        $card = $this->activeCard();
        $card->releaseEvents();
        $cards->save($card);

        $handler = new ChangeSpendingLimitsCommandHandler(
            $cards,
            $outbox,
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-10T10:00:00Z')),
        );

        $handler(new ChangeSpendingLimitsCommand(
            cardId: $card->id()->toString(),
            perTransactionLimit: 200_00,
            dailyLimit: 400_00,
            monthlyLimit: 2_000_00,
            currency: 'USD',
        ));

        self::assertSame(200_00, $card->spendingLimits()->perTransaction->amountInMinorUnits);
        self::assertSame(400_00, $card->spendingLimits()->daily->amountInMinorUnits);
        self::assertSame(2_000_00, $card->spendingLimits()->monthly->amountInMinorUnits);
        self::assertInstanceOf(SpendingLimitsChanged::class, $outbox->events[0]);
    }

    public function test_throws_when_the_card_does_not_exist(): void
    {
        $handler = new ChangeSpendingLimitsCommandHandler(
            new InMemoryCardRepository(),
            new InMemoryOutboxRepository(),
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-10T10:00:00Z')),
        );

        $this->expectException(CardNotFoundException::class);

        $handler(new ChangeSpendingLimitsCommand(
            cardId: '018e7c8a-1d2b-7d3e-9abc-def012345678',
            perTransactionLimit: 100,
            dailyLimit: 200,
            monthlyLimit: 500,
            currency: 'USD',
        ));
    }

    private function activeCard(): Card
    {
        $card = Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(50_00), Money::usd(100_00), Money::usd(500_00)),
            Money::usd(1_000_00),
            [MerchantCategoryCode::rideSharing()],
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );
        $card->activate(new \DateTimeImmutable('2026-04-01T10:30:00Z'));

        return $card;
    }
}
