<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\SuspendCardCommand;
use App\Application\Card\SuspendCardCommandHandler;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardStatus;
use App\Domain\Card\Event\CardSuspended;
use App\Domain\Card\Exception\CardNotFoundException;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryCardRepository;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class SuspendCardCommandHandlerTest extends TestCase
{
    public function test_suspends_an_active_card_and_emits_the_event(): void
    {
        $cards = new InMemoryCardRepository();
        $outbox = new InMemoryOutboxRepository();
        $card = $this->activeCard();
        $card->releaseEvents();
        $cards->save($card);

        $handler = new SuspendCardCommandHandler(
            $cards,
            $outbox,
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-05T09:00:00Z')),
        );

        $handler(new SuspendCardCommand($card->id()->toString(), 'Lost card reported'));

        self::assertSame(CardStatus::Suspended, $card->status());
        self::assertInstanceOf(CardSuspended::class, $outbox->events[0]);
        self::assertSame('Lost card reported', $outbox->events[0]->toArray()['reason']);
    }

    public function test_throws_when_the_card_does_not_exist(): void
    {
        $handler = new SuspendCardCommandHandler(
            new InMemoryCardRepository(),
            new InMemoryOutboxRepository(),
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-05T09:00:00Z')),
        );

        $this->expectException(CardNotFoundException::class);

        $handler(new SuspendCardCommand('018e7c8a-1d2b-7d3e-9abc-def012345678', 'reason'));
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
