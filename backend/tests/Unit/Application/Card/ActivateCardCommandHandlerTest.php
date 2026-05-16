<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\ActivateCardCommand;
use App\Application\Card\ActivateCardCommandHandler;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardStatus;
use App\Domain\Card\Event\CardActivated;
use App\Domain\Card\Exception\CardNotFoundException;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryCardRepository;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class ActivateCardCommandHandlerTest extends TestCase
{
    public function test_activate_loads_the_card_activates_it_and_emits_the_event(): void
    {
        $cards = new InMemoryCardRepository();
        $outbox = new InMemoryOutboxRepository();
        $card = $this->pendingCard();
        $card->releaseEvents();
        $cards->save($card);

        $handler = new ActivateCardCommandHandler(
            $cards,
            $outbox,
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-01T10:30:00Z')),
        );

        $handler(new ActivateCardCommand($card->id()->toString()));

        self::assertSame(CardStatus::Active, $card->status());
        self::assertCount(1, $outbox->events);
        self::assertInstanceOf(CardActivated::class, $outbox->events[0]);
    }

    public function test_activate_throws_when_the_card_does_not_exist(): void
    {
        $handler = new ActivateCardCommandHandler(
            new InMemoryCardRepository(),
            new InMemoryOutboxRepository(),
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-01T10:30:00Z')),
        );

        $this->expectException(CardNotFoundException::class);

        $handler(new ActivateCardCommand('018e7c8a-1d2b-7d3e-9abc-def012345678'));
    }

    private function pendingCard(): Card
    {
        return Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(50_00), Money::usd(100_00), Money::usd(500_00)),
            Money::usd(1_000_00),
            [MerchantCategoryCode::rideSharing()],
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );
    }
}
