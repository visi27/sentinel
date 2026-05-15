<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Card;

use App\Domain\Card\CardStatus;
use PHPUnit\Framework\TestCase;

final class CardStatusTest extends TestCase
{
    public function test_only_active_returns_true_from_is_active(): void
    {
        self::assertTrue(CardStatus::Active->isActive());
        self::assertFalse(CardStatus::Pending->isActive());
        self::assertFalse(CardStatus::Suspended->isActive());
        self::assertFalse(CardStatus::Closed->isActive());
    }

    /**
     * @dataProvider transitionMatrix
     */
    public function test_can_transition_to_encodes_the_state_machine(
        CardStatus $from,
        CardStatus $to,
        bool $allowed,
    ): void {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{CardStatus, CardStatus, bool}>
     */
    public static function transitionMatrix(): iterable
    {
        yield 'pending -> active' => [CardStatus::Pending, CardStatus::Active, true];
        yield 'pending -> suspended' => [CardStatus::Pending, CardStatus::Suspended, false];
        yield 'pending -> closed' => [CardStatus::Pending, CardStatus::Closed, true];

        yield 'active -> suspended' => [CardStatus::Active, CardStatus::Suspended, true];
        yield 'active -> closed' => [CardStatus::Active, CardStatus::Closed, true];
        yield 'active -> pending' => [CardStatus::Active, CardStatus::Pending, false];

        yield 'suspended -> active' => [CardStatus::Suspended, CardStatus::Active, true];
        yield 'suspended -> closed' => [CardStatus::Suspended, CardStatus::Closed, true];
        yield 'suspended -> pending' => [CardStatus::Suspended, CardStatus::Pending, false];

        yield 'closed -> any (terminal)' => [CardStatus::Closed, CardStatus::Active, false];
        yield 'closed -> closed (no self-transition)' => [CardStatus::Closed, CardStatus::Closed, false];
    }
}
