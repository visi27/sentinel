<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Card;

use App\Application\Card\AuthorizeCardCommand;
use App\Application\Card\AuthorizeCardCommandHandler;
use App\Application\Card\MerchantLocationData;
use App\Domain\Authorization\Event\CardAuthorizationApproved;
use App\Domain\Authorization\Event\CardAuthorizationDeclined;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Tests\Unit\Application\Fakes\FixedClock;
use App\Tests\Unit\Application\Fakes\InMemoryAuthorizationRepository;
use App\Tests\Unit\Application\Fakes\InMemoryCardRepository;
use App\Tests\Unit\Application\Fakes\InMemoryOutboxRepository;
use App\Tests\Unit\Application\Fakes\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class AuthorizeCardCommandHandlerTest extends TestCase
{
    private InMemoryCardRepository $cards;
    private InMemoryAuthorizationRepository $authorizations;
    private InMemoryOutboxRepository $outbox;
    private FixedClock $clock;
    private AuthorizeCardCommandHandler $handler;

    protected function setUp(): void
    {
        $this->cards = new InMemoryCardRepository();
        $this->authorizations = new InMemoryAuthorizationRepository();
        $this->outbox = new InMemoryOutboxRepository();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:57Z'));
        $this->handler = new AuthorizeCardCommandHandler(
            $this->cards,
            $this->authorizations,
            $this->outbox,
            new SynchronousTransactionManager(),
            $this->clock,
        );
    }

    public function test_an_approved_authorization_saves_the_card_and_records_an_approved_event(): void
    {
        $card = $this->seedActiveCard(balance: Money::usd(10_000));
        $command = $this->command($card, amount: 2_500, processorAuthId: 'auth_001');

        $decision = ($this->handler)($command);

        self::assertSame('approved', $decision->status);
        self::assertNull($decision->declineReason);
        self::assertSame(1, $this->cards->saveCount, 'card should be persisted exactly once');
        self::assertCount(1, $this->authorizations->all());
        self::assertSame(7_500, $card->availableBalance()->amountInMinorUnits);
        self::assertCount(1, $this->outbox->events);
        self::assertInstanceOf(CardAuthorizationApproved::class, $this->outbox->events[0]);
    }

    public function test_a_declined_authorization_records_the_authorization_but_does_not_save_the_card(): void
    {
        $card = $this->seedActiveCard(balance: Money::usd(10));
        $balanceBefore = $card->availableBalance()->amountInMinorUnits;

        $decision = ($this->handler)($this->command($card, amount: 5_000, processorAuthId: 'auth_002'));

        self::assertSame('declined', $decision->status);
        self::assertSame('INSUFFICIENT_FUNDS', $decision->declineReason);
        self::assertSame(0, $this->cards->saveCount, 'a decline must not write the card');
        self::assertSame($balanceBefore, $card->availableBalance()->amountInMinorUnits);
        self::assertCount(1, $this->authorizations->all());
        self::assertCount(1, $this->outbox->events);
        self::assertInstanceOf(CardAuthorizationDeclined::class, $this->outbox->events[0]);
    }

    public function test_a_missing_card_yields_a_declined_authorization_for_audit(): void
    {
        // Card was never seeded; the cardId in the command is a valid UUID
        // but resolves to nothing in the repository.
        $command = new AuthorizeCardCommand(
            processorAuthId: 'auth_003',
            cardId: '018e7c8a-1d2b-7d3e-9abc-def012345678',
            amount: 1_000,
            currency: 'USD',
            merchantName: 'Uber',
            merchantCategoryCode: '4121',
            merchantLocation: null,
            requestedAt: new \DateTimeImmutable('2026-05-14T12:34:56Z'),
        );

        $decision = ($this->handler)($command);

        self::assertSame('declined', $decision->status);
        self::assertSame('CARD_NOT_ACTIVE', $decision->declineReason);
        // The audit row still gets written.
        self::assertCount(1, $this->authorizations->all());
        self::assertSame(0, $this->cards->saveCount);
    }

    public function test_idempotency_returns_the_original_decision_without_reprocessing(): void
    {
        $card = $this->seedActiveCard(balance: Money::usd(10_000));

        $first = ($this->handler)($this->command($card, amount: 2_500, processorAuthId: 'auth_dupe'));
        $balanceAfterFirst = $card->availableBalance()->amountInMinorUnits;

        $second = ($this->handler)($this->command($card, amount: 9_999, processorAuthId: 'auth_dupe'));

        // The replay must reuse the original outcome, NOT re-decide.
        self::assertSame($first->authorizationId, $second->authorizationId);
        self::assertSame($first->status, $second->status);
        self::assertSame(1, $this->cards->saveCount, 'idempotent replay must not save the card again');
        self::assertSame($balanceAfterFirst, $card->availableBalance()->amountInMinorUnits);
        self::assertCount(1, $this->authorizations->all());
    }

    public function test_merchant_location_is_propagated_when_supplied(): void
    {
        $card = $this->seedActiveCard(balance: Money::usd(10_000));

        $command = $this->command(
            $card,
            amount: 1_500,
            processorAuthId: 'auth_004',
            location: new MerchantLocationData('Boston', 'MA', 'US'),
        );
        ($this->handler)($command);

        $stored = $this->authorizations->all()[0];
        self::assertNotNull($stored->merchant()->location);
        self::assertSame('Boston', $stored->merchant()->location->city);
    }

    private function seedActiveCard(Money $balance): Card
    {
        $card = Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(10_000), Money::usd(20_000), Money::usd(100_000)),
            $balance,
            [MerchantCategoryCode::rideSharing(), MerchantCategoryCode::restaurants()],
            new \DateTimeImmutable('2026-05-01T10:00:00Z'),
        );
        $card->activate(new \DateTimeImmutable('2026-05-01T10:30:00Z'));
        $card->releaseEvents();
        $this->cards->save($card);
        // Seeding via save() incremented the counter; reset so tests assert
        // only on saves the handler itself triggers.
        $this->cards->saveCount = 0;

        return $card;
    }

    private function command(
        Card $card,
        int $amount,
        string $processorAuthId,
        ?MerchantLocationData $location = null,
    ): AuthorizeCardCommand {
        return new AuthorizeCardCommand(
            processorAuthId: $processorAuthId,
            cardId: $card->id()->toString(),
            amount: $amount,
            currency: 'USD',
            merchantName: 'Uber',
            merchantCategoryCode: '4121',
            merchantLocation: $location,
            requestedAt: new \DateTimeImmutable('2026-05-14T12:34:56Z'),
        );
    }
}
