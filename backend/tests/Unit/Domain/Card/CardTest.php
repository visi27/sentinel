<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Card;

use App\Domain\Authorization\DeclineReason;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardStatus;
use App\Domain\Card\Event\CardActivated;
use App\Domain\Card\Event\CardClosed;
use App\Domain\Card\Event\CardIssued;
use App\Domain\Card\Event\CardSuspended;
use App\Domain\Card\Event\SpendingLimitsChanged;
use App\Domain\Card\Exception\InvalidCardStateTransitionException;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    // --- Issuance --------------------------------------------------------

    public function test_issue_creates_a_pending_card_with_the_initial_balance_and_zero_spend(): void
    {
        $card = $this->issuedCard(balance: Money::usd(100_00));

        self::assertSame(CardStatus::Pending, $card->status());
        self::assertSame(100_00, $card->availableBalance()->amountInMinorUnits);
        self::assertTrue($card->dailySpend()->isZero());
        self::assertTrue($card->monthlySpend()->isZero());
        self::assertNull($card->activatedAt());
        self::assertNull($card->closedAt());
        self::assertSame(0, $card->version());
    }

    public function test_issue_raises_a_card_issued_event(): void
    {
        $card = $this->issuedCard();
        $events = $card->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(CardIssued::class, $events[0]);
        self::assertSame($card->id()->toString(), $events[0]->aggregateId());
        self::assertSame('Card', $events[0]->aggregateType());
    }

    // --- State transitions ----------------------------------------------

    public function test_activate_moves_a_pending_card_to_active(): void
    {
        $card = $this->issuedCard();
        $card->releaseEvents(); // drop the CardIssued event

        $card->activate($this->at('2026-04-01T10:00:00Z'));

        self::assertSame(CardStatus::Active, $card->status());
        self::assertEquals($this->at('2026-04-01T10:00:00Z'), $card->activatedAt());
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardActivated::class, $events[0]);
    }

    public function test_activate_rejects_a_card_that_is_not_pending(): void
    {
        $card = $this->activeCard();

        $this->expectException(InvalidCardStateTransitionException::class);

        $card->activate($this->at('2026-04-02T10:00:00Z'));
    }

    public function test_suspend_moves_an_active_card_to_suspended_with_a_reason(): void
    {
        $card = $this->activeCard();
        $card->releaseEvents();

        $card->suspend('Lost card reported by cardholder', $this->at('2026-04-05T09:00:00Z'));

        self::assertSame(CardStatus::Suspended, $card->status());
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(CardSuspended::class, $event);
        self::assertSame('Lost card reported by cardholder', $event->toArray()['reason']);
    }

    public function test_suspend_rejects_a_card_that_is_not_active(): void
    {
        $card = $this->issuedCard();

        $this->expectException(InvalidCardStateTransitionException::class);

        $card->suspend('any', $this->at('2026-04-05T09:00:00Z'));
    }

    public function test_unsuspend_returns_a_suspended_card_to_active(): void
    {
        $card = $this->activeCard();
        $card->suspend('reason', $this->at('2026-04-05T09:00:00Z'));
        $card->releaseEvents();

        $card->unsuspend($this->at('2026-04-06T09:00:00Z'));

        self::assertSame(CardStatus::Active, $card->status());
        $events = $card->releaseEvents();
        self::assertInstanceOf(CardActivated::class, $events[0]);
    }

    public function test_unsuspend_rejects_a_card_that_is_not_suspended(): void
    {
        $card = $this->activeCard();

        $this->expectException(InvalidCardStateTransitionException::class);

        $card->unsuspend($this->at('2026-04-06T09:00:00Z'));
    }

    public function test_close_works_from_pending_active_and_suspended(): void
    {
        foreach ([$this->issuedCard(), $this->activeCard(), $this->suspendedCard()] as $card) {
            $card->releaseEvents();
            $card->close($this->at('2026-04-10T10:00:00Z'));

            self::assertSame(CardStatus::Closed, $card->status());
            self::assertEquals($this->at('2026-04-10T10:00:00Z'), $card->closedAt());
            $events = $card->releaseEvents();
            self::assertInstanceOf(CardClosed::class, $events[0]);
        }
    }

    public function test_close_is_terminal(): void
    {
        $card = $this->activeCard();
        $card->close($this->at('2026-04-10T10:00:00Z'));

        $this->expectException(InvalidCardStateTransitionException::class);

        $card->close($this->at('2026-04-11T10:00:00Z'));
    }

    // --- changeSpendingLimits -------------------------------------------

    public function test_change_spending_limits_updates_limits_and_raises_an_event(): void
    {
        $card = $this->activeCard();
        $card->releaseEvents();

        $newLimits = $this->limits(perTx: 200_00, daily: 400_00, monthly: 2_000_00);
        $card->changeSpendingLimits($newLimits, $this->at('2026-04-05T10:00:00Z'));

        self::assertTrue($newLimits->equals($card->spendingLimits()));
        $events = $card->releaseEvents();
        self::assertInstanceOf(SpendingLimitsChanged::class, $events[0]);
    }

    public function test_change_spending_limits_is_rejected_on_a_closed_card(): void
    {
        $card = $this->activeCard();
        $card->close($this->at('2026-04-10T10:00:00Z'));

        $this->expectException(InvalidCardStateTransitionException::class);

        $card->changeSpendingLimits(
            $this->limits(100, 200, 500),
            $this->at('2026-04-11T10:00:00Z'),
        );
    }

    // --- authorize: declines --------------------------------------------

    public function test_authorize_on_a_non_active_card_declines_card_not_active(): void
    {
        $card = $this->issuedCard(); // still Pending

        $result = $card->authorize(Money::usd(100), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::CardNotActive, $result->declineReason);
    }

    public function test_authorize_with_a_blocked_merchant_category_declines(): void
    {
        $card = $this->activeCard();

        $blocked = new Merchant('Tesla Charger', MerchantCategoryCode::gasStations());
        $result = $card->authorize(Money::usd(100), $blocked, $this->at('2026-05-01T12:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::MerchantCategoryBlocked, $result->declineReason);
    }

    public function test_authorize_over_the_per_transaction_limit_declines(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 100_00, daily: 1_000_00, monthly: 5_000_00),
            balance: Money::usd(10_000_00),
        );

        $result = $card->authorize(Money::usd(150_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::ExceedsPerTransactionLimit, $result->declineReason);
    }

    public function test_authorize_exactly_at_the_per_transaction_limit_is_approved(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 100_00, daily: 200_00, monthly: 1_000_00),
            balance: Money::usd(500_00),
        );

        $result = $card->authorize(Money::usd(100_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertTrue($result->isApproved);
    }

    public function test_authorize_one_minor_unit_under_the_limit_is_approved(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 100_00, daily: 200_00, monthly: 1_000_00),
            balance: Money::usd(500_00),
        );

        $result = $card->authorize(Money::usd(99_99), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertTrue($result->isApproved);
    }

    public function test_authorize_over_the_daily_limit_declines(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 100_00, daily: 100_00, monthly: 1_000_00),
            balance: Money::usd(500_00),
        );
        // Spend up to the daily limit first.
        $card->authorize(Money::usd(100_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        $result = $card->authorize(Money::usd(1), $this->aMerchant(), $this->at('2026-05-01T13:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::ExceedsDailyLimit, $result->declineReason);
    }

    public function test_authorize_over_the_monthly_limit_declines(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 50_00, daily: 50_00, monthly: 100_00),
            balance: Money::usd(500_00),
        );
        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));
        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-02T12:00:00Z'));

        $result = $card->authorize(Money::usd(1), $this->aMerchant(), $this->at('2026-05-03T12:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::ExceedsMonthlyLimit, $result->declineReason);
    }

    public function test_authorize_over_the_available_balance_declines(): void
    {
        $card = $this->activeCard(balance: Money::usd(50));

        $result = $card->authorize(Money::usd(100), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::InsufficientFunds, $result->declineReason);
    }

    // --- authorize: approvals and state mutation ------------------------

    public function test_successful_authorize_decrements_balance_and_increments_spend(): void
    {
        $card = $this->activeCard(balance: Money::usd(500));
        $startingVersion = $card->version();

        $result = $card->authorize(Money::usd(120), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertTrue($result->isApproved);
        self::assertSame(380, $card->availableBalance()->amountInMinorUnits);
        self::assertSame(120, $card->dailySpend()->amountInMinorUnits);
        self::assertSame(120, $card->monthlySpend()->amountInMinorUnits);
        self::assertSame($startingVersion + 1, $card->version());
    }

    public function test_authorize_does_not_raise_an_event_itself(): void
    {
        // The caller (an application service / the Authorization aggregate)
        // owns the event emission; the Card's authorize() is silent.
        $card = $this->activeCard();
        $card->releaseEvents();

        $card->authorize(Money::usd(100), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertSame([], $card->releaseEvents());
    }

    public function test_a_decline_does_not_mutate_balance_or_version(): void
    {
        $card = $this->activeCard(balance: Money::usd(50));
        $balanceBefore = $card->availableBalance()->amountInMinorUnits;
        $versionBefore = $card->version();

        $card->authorize(Money::usd(100), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertSame($balanceBefore, $card->availableBalance()->amountInMinorUnits);
        self::assertSame($versionBefore, $card->version());
    }

    public function test_daily_spend_accumulates_within_a_single_day(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 100_00, daily: 100_00, monthly: 1_000_00),
            balance: Money::usd(500_00),
        );

        $card->authorize(Money::usd(40_00), $this->aMerchant(), $this->at('2026-05-01T09:00:00Z'));
        $card->authorize(Money::usd(30_00), $this->aMerchant(), $this->at('2026-05-01T13:00:00Z'));

        self::assertSame(70_00, $card->dailySpend()->amountInMinorUnits);
    }

    public function test_daily_spend_resets_when_the_calendar_day_rolls_over(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 50_00, daily: 50_00, monthly: 1_000_00),
            balance: Money::usd(500_00),
        );

        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-01T23:00:00Z'));
        // The next day: the rule engine treats yesterday's spend as cleared.
        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-02T01:00:00Z'));

        self::assertSame(50_00, $card->dailySpend()->amountInMinorUnits);
    }

    public function test_monthly_spend_resets_when_the_month_rolls_over(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 50_00, daily: 100_00, monthly: 100_00),
            balance: Money::usd(500_00),
        );

        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-04-30T12:00:00Z'));
        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));

        self::assertSame(50_00, $card->monthlySpend()->amountInMinorUnits);
    }

    public function test_monthly_spend_persists_across_days_within_the_same_month(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 50_00, daily: 50_00, monthly: 200_00),
            balance: Money::usd(500_00),
        );

        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-01T12:00:00Z'));
        $card->authorize(Money::usd(50_00), $this->aMerchant(), $this->at('2026-05-15T12:00:00Z'));

        self::assertSame(100_00, $card->monthlySpend()->amountInMinorUnits);
    }

    // --- Rule ordering --------------------------------------------------

    public function test_card_not_active_takes_precedence_over_other_decline_reasons(): void
    {
        // A suspended card with a blocked merchant and an over-limit amount
        // still declines with the first rule violated: CardNotActive.
        $card = $this->activeCard(balance: Money::usd(1));
        $card->suspend('reason', $this->at('2026-04-05T09:00:00Z'));

        $result = $card->authorize(
            Money::usd(10_000_00),
            new Merchant('Blocked Vendor', MerchantCategoryCode::gasStations()),
            $this->at('2026-05-01T12:00:00Z'),
        );

        self::assertSame(DeclineReason::CardNotActive, $result->declineReason);
    }

    public function test_merchant_category_block_takes_precedence_over_limit_checks(): void
    {
        $card = $this->activeCard(
            limits: $this->limits(perTx: 1, daily: 1, monthly: 1),
            balance: Money::usd(0),
        );

        $result = $card->authorize(
            Money::usd(10_000_00),
            new Merchant('Blocked Vendor', MerchantCategoryCode::gasStations()),
            $this->at('2026-05-01T12:00:00Z'),
        );

        self::assertSame(DeclineReason::MerchantCategoryBlocked, $result->declineReason);
    }

    // --- Test helpers ---------------------------------------------------

    private function issuedCard(
        ?SpendingLimits $limits = null,
        ?Money $balance = null,
    ): Card {
        return Card::issue(
            CardholderId::generate(),
            $limits ?? $this->limits(50_00, 100_00, 500_00),
            $balance ?? Money::usd(500_00),
            [MerchantCategoryCode::rideSharing(), MerchantCategoryCode::restaurants()],
            $this->at('2026-04-01T10:00:00Z'),
        );
    }

    private function activeCard(
        ?SpendingLimits $limits = null,
        ?Money $balance = null,
    ): Card {
        $card = $this->issuedCard($limits, $balance);
        $card->activate($this->at('2026-04-01T10:30:00Z'));

        return $card;
    }

    private function suspendedCard(): Card
    {
        $card = $this->activeCard();
        $card->suspend('any', $this->at('2026-04-02T09:00:00Z'));

        return $card;
    }

    private function aMerchant(): Merchant
    {
        return new Merchant('Uber', MerchantCategoryCode::rideSharing());
    }

    private function limits(int $perTx, int $daily, int $monthly): SpendingLimits
    {
        return new SpendingLimits(
            Money::usd($perTx),
            Money::usd($daily),
            Money::usd($monthly),
        );
    }

    private function at(string $iso8601Utc): \DateTimeImmutable
    {
        return new \DateTimeImmutable($iso8601Utc);
    }
}
