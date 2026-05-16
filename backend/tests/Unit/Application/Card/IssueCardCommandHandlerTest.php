<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\IssueCardCommand;
use App\Application\Card\IssueCardCommandHandler;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardId;
use App\Domain\Card\CardStatus;
use App\Domain\Card\Event\CardIssued;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryCardRepository;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class IssueCardCommandHandlerTest extends TestCase
{
    public function test_issues_a_pending_card_with_the_requested_limits_and_emits_the_event(): void
    {
        $cards = new InMemoryCardRepository();
        $outbox = new InMemoryOutboxRepository();
        $cardholderId = CardholderId::generate();
        $handler = new IssueCardCommandHandler(
            $cards,
            $outbox,
            new SynchronousTransactionManager(),
            new FixedClock(new \DateTimeImmutable('2026-04-01T10:00:00Z')),
        );

        $newCardId = $handler(new IssueCardCommand(
            cardholderId: $cardholderId->toString(),
            perTransactionLimit: 50_00,
            dailyLimit: 100_00,
            monthlyLimit: 500_00,
            initialBalance: 1_000_00,
            currency: 'USD',
            allowedMerchantCategoryCodes: ['4121', '5812'],
        ));

        $card = $cards->findById(CardId::fromString($newCardId));
        self::assertNotNull($card);
        self::assertSame(CardStatus::Pending, $card->status());
        self::assertSame(1_000_00, $card->availableBalance()->amountInMinorUnits);
        self::assertCount(2, $card->allowedMerchantCategoryCodes());
        self::assertCount(1, $outbox->events);
        self::assertInstanceOf(CardIssued::class, $outbox->events[0]);
    }
}
