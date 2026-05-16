<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Authorization\DeclineReason;
use App\Domain\Card\Event\CardActivated;
use App\Domain\Card\Event\CardClosed;
use App\Domain\Card\Event\CardIssued;
use App\Domain\Card\Event\CardSuspended;
use App\Domain\Card\Event\SpendingLimitsChanged;
use App\Domain\Card\Exception\InvalidCardStateTransitionException;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Domain\Shared\AggregateRoot;

/**
 * The Card aggregate root. It owns the authorization rules engine and the
 * lifecycle state machine; the application layer only loads it, calls a
 * method, and persists the result.
 */
final class Card extends AggregateRoot
{
    /**
     * @param list<MerchantCategoryCode> $allowedMerchantCategoryCodes
     */
    private function __construct(
        private readonly CardId $id,
        private readonly CardholderId $cardholderId,
        private CardStatus $status,
        private SpendingLimits $spendingLimits,
        private Money $availableBalance,
        private Money $dailySpend,
        private Money $monthlySpend,
        private readonly array $allowedMerchantCategoryCodes,
        private readonly \DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $activatedAt,
        private ?\DateTimeImmutable $closedAt,
        private ?\DateTimeImmutable $lastSpendDate,
        private int $version,
    ) {
    }

    /**
     * Issues a new card. It starts Pending — a separate activate() step is
     * required before it can authorize.
     *
     * @param list<MerchantCategoryCode> $allowedMerchantCategories
     */
    public static function issue(
        CardholderId $cardholderId,
        SpendingLimits $limits,
        Money $initialBalance,
        array $allowedMerchantCategories,
        \DateTimeImmutable $issuedAt,
    ): self {
        $card = new self(
            CardId::generate(),
            $cardholderId,
            CardStatus::Pending,
            $limits,
            $initialBalance,
            Money::zero($initialBalance->currency),
            Money::zero($initialBalance->currency),
            $allowedMerchantCategories,
            $issuedAt,
            null,
            null,
            null,
            0,
        );

        $card->raise(new CardIssued($card->id, $cardholderId, $issuedAt));

        return $card;
    }

    public function activate(\DateTimeImmutable $activatedAt): void
    {
        if (CardStatus::Pending !== $this->status) {
            throw InvalidCardStateTransitionException::from($this->status, CardStatus::Active);
        }

        $this->status = CardStatus::Active;
        $this->activatedAt = $activatedAt;
        $this->raise(new CardActivated($this->id, $activatedAt));
    }

    public function suspend(string $reason, \DateTimeImmutable $suspendedAt): void
    {
        if (CardStatus::Active !== $this->status) {
            throw InvalidCardStateTransitionException::from($this->status, CardStatus::Suspended);
        }

        $this->status = CardStatus::Suspended;
        $this->raise(new CardSuspended($this->id, $reason, $suspendedAt));
    }

    public function unsuspend(\DateTimeImmutable $unsuspendedAt): void
    {
        if (CardStatus::Suspended !== $this->status) {
            throw InvalidCardStateTransitionException::from($this->status, CardStatus::Active);
        }

        $this->status = CardStatus::Active;
        $this->raise(new CardActivated($this->id, $unsuspendedAt));
    }

    public function close(\DateTimeImmutable $closedAt): void
    {
        if (!$this->status->canTransitionTo(CardStatus::Closed)) {
            throw InvalidCardStateTransitionException::from($this->status, CardStatus::Closed);
        }

        $this->status = CardStatus::Closed;
        $this->closedAt = $closedAt;
        $this->raise(new CardClosed($this->id, $closedAt));
    }

    public function changeSpendingLimits(SpendingLimits $newLimits, \DateTimeImmutable $changedAt): void
    {
        if (CardStatus::Closed === $this->status) {
            throw InvalidCardStateTransitionException::cannotChangeLimitsWhenClosed();
        }

        $this->spendingLimits = $newLimits;
        $this->raise(new SpendingLimitsChanged($this->id, $newLimits, $changedAt));
    }

    /**
     * Evaluates an authorization request against the card's rules. Rules are
     * checked in a fixed order so the decline reason is always the first rule
     * violated. On approval the card's balance and spend counters are updated
     * as a side effect; no event is raised here — the calling context records
     * the Authorization aggregate and emits the event.
     */
    public function authorize(Money $amount, Merchant $merchant, \DateTimeImmutable $now): AuthorizationResult
    {
        if (!$this->status->isActive()) {
            return AuthorizationResult::declined(DeclineReason::CardNotActive);
        }

        if (!$this->allowsMerchantCategory($merchant->categoryCode)) {
            return AuthorizationResult::declined(DeclineReason::MerchantCategoryBlocked);
        }

        if ($amount->isGreaterThan($this->spendingLimits->perTransaction)) {
            return AuthorizationResult::declined(DeclineReason::ExceedsPerTransactionLimit);
        }

        // Spend counters roll over lazily: a request on a new day or month
        // sees the prior window's total as zero.
        $dailySpend = $this->currentDailySpend($now);
        if ($dailySpend->add($amount)->isGreaterThan($this->spendingLimits->daily)) {
            return AuthorizationResult::declined(DeclineReason::ExceedsDailyLimit);
        }

        $monthlySpend = $this->currentMonthlySpend($now);
        if ($monthlySpend->add($amount)->isGreaterThan($this->spendingLimits->monthly)) {
            return AuthorizationResult::declined(DeclineReason::ExceedsMonthlyLimit);
        }

        if ($amount->isGreaterThan($this->availableBalance)) {
            return AuthorizationResult::declined(DeclineReason::InsufficientFunds);
        }

        $this->availableBalance = $this->availableBalance->subtract($amount);
        $this->dailySpend = $dailySpend->add($amount);
        $this->monthlySpend = $monthlySpend->add($amount);
        $this->lastSpendDate = $now;
        // Doctrine increments the version field automatically on UPDATE of a
        // versioned entity; manually bumping it here conflicts with that
        // handling. A concurrent authorization that loaded the same row
        // still fails the WHERE version = ? clause on commit.

        return AuthorizationResult::approved();
    }

    public function id(): CardId
    {
        return $this->id;
    }

    public function cardholderId(): CardholderId
    {
        return $this->cardholderId;
    }

    public function status(): CardStatus
    {
        return $this->status;
    }

    public function spendingLimits(): SpendingLimits
    {
        return $this->spendingLimits;
    }

    public function availableBalance(): Money
    {
        return $this->availableBalance;
    }

    public function dailySpend(): Money
    {
        return $this->dailySpend;
    }

    public function monthlySpend(): Money
    {
        return $this->monthlySpend;
    }

    /**
     * @return list<MerchantCategoryCode>
     */
    public function allowedMerchantCategoryCodes(): array
    {
        return $this->allowedMerchantCategoryCodes;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function activatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    private function allowsMerchantCategory(MerchantCategoryCode $categoryCode): bool
    {
        foreach ($this->allowedMerchantCategoryCodes as $allowed) {
            if ($allowed->equals($categoryCode)) {
                return true;
            }
        }

        return false;
    }

    private function currentDailySpend(\DateTimeImmutable $now): Money
    {
        if (null === $this->lastSpendDate || !$this->isSameDay($this->lastSpendDate, $now)) {
            return Money::zero($this->availableBalance->currency);
        }

        return $this->dailySpend;
    }

    private function currentMonthlySpend(\DateTimeImmutable $now): Money
    {
        if (null === $this->lastSpendDate || !$this->isSameMonth($this->lastSpendDate, $now)) {
            return Money::zero($this->availableBalance->currency);
        }

        return $this->monthlySpend;
    }

    private function isSameDay(\DateTimeImmutable $a, \DateTimeImmutable $b): bool
    {
        return $this->utcFormat($a, 'Y-m-d') === $this->utcFormat($b, 'Y-m-d');
    }

    private function isSameMonth(\DateTimeImmutable $a, \DateTimeImmutable $b): bool
    {
        return $this->utcFormat($a, 'Y-m') === $this->utcFormat($b, 'Y-m');
    }

    private function utcFormat(\DateTimeImmutable $dateTime, string $format): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format($format);
    }
}
