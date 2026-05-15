<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Authorization;

use App\Domain\Authorization\Authorization;
use App\Domain\Authorization\AuthorizationId;
use App\Domain\Authorization\AuthorizationStatus;
use App\Domain\Authorization\DeclineReason;
use App\Domain\Authorization\Event\AuthorizationReversed;
use App\Domain\Authorization\Event\CardAuthorizationApproved;
use App\Domain\Authorization\Event\CardAuthorizationDeclined;
use App\Domain\Authorization\Exception\InvalidAuthorizationStateException;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardId;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    public function test_record_approved_creates_an_approved_authorization_and_raises_approved_event(): void
    {
        $cardId = CardId::generate();
        $authId = AuthorizationId::generate();

        $authorization = Authorization::record(
            $authId,
            $cardId,
            'auth_abc123',
            Money::usd(5_000),
            $this->aMerchant(),
            AuthorizationResult::approved(),
            $this->at('2026-05-14T12:34:56Z'),
            $this->at('2026-05-14T12:34:56Z'),
        );

        self::assertTrue($authorization->isApproved());
        self::assertSame(AuthorizationStatus::Approved, $authorization->status());
        self::assertNull($authorization->declineReason());
        $events = $authorization->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardAuthorizationApproved::class, $events[0]);
        self::assertSame($authId->toString(), $events[0]->aggregateId());
    }

    public function test_record_declined_creates_a_declined_authorization_with_the_reason_and_event(): void
    {
        $authorization = Authorization::record(
            AuthorizationId::generate(),
            CardId::generate(),
            'auth_abc123',
            Money::usd(5_000),
            $this->aMerchant(),
            AuthorizationResult::declined(DeclineReason::InsufficientFunds),
            $this->at('2026-05-14T12:34:56Z'),
            $this->at('2026-05-14T12:34:56Z'),
        );

        self::assertFalse($authorization->isApproved());
        self::assertSame(AuthorizationStatus::Declined, $authorization->status());
        self::assertSame(DeclineReason::InsufficientFunds, $authorization->declineReason());
        $events = $authorization->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(CardAuthorizationDeclined::class, $event);
        self::assertSame('INSUFFICIENT_FUNDS', $event->toArray()['decline_reason']);
    }

    public function test_reverse_marks_an_approved_authorization_reversed_and_raises_event(): void
    {
        $authorization = $this->anApprovedAuthorization();
        $authorization->releaseEvents();

        $authorization->reverse($this->at('2026-05-15T09:00:00Z'));

        self::assertSame(AuthorizationStatus::Reversed, $authorization->status());
        self::assertEquals($this->at('2026-05-15T09:00:00Z'), $authorization->reversedAt());
        $events = $authorization->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AuthorizationReversed::class, $events[0]);
    }

    public function test_reverse_rejects_a_declined_authorization(): void
    {
        $authorization = Authorization::record(
            AuthorizationId::generate(),
            CardId::generate(),
            'auth_abc123',
            Money::usd(5_000),
            $this->aMerchant(),
            AuthorizationResult::declined(DeclineReason::InsufficientFunds),
            $this->at('2026-05-14T12:34:56Z'),
            $this->at('2026-05-14T12:34:56Z'),
        );

        $this->expectException(InvalidAuthorizationStateException::class);

        $authorization->reverse($this->at('2026-05-15T09:00:00Z'));
    }

    public function test_reverse_rejects_an_already_reversed_authorization(): void
    {
        $authorization = $this->anApprovedAuthorization();
        $authorization->reverse($this->at('2026-05-15T09:00:00Z'));

        $this->expectException(InvalidAuthorizationStateException::class);

        $authorization->reverse($this->at('2026-05-15T10:00:00Z'));
    }

    public function test_getters_round_trip_the_constructor_arguments(): void
    {
        $cardId = CardId::generate();
        $authId = AuthorizationId::generate();
        $merchant = $this->aMerchant();
        $requestedAt = $this->at('2026-05-14T12:34:56Z');
        $decidedAt = $this->at('2026-05-14T12:34:57Z');

        $authorization = Authorization::record(
            $authId,
            $cardId,
            'auth_abc123',
            Money::usd(5_000),
            $merchant,
            AuthorizationResult::approved(),
            $requestedAt,
            $decidedAt,
        );

        self::assertTrue($authId->equals($authorization->id()));
        self::assertTrue($cardId->equals($authorization->cardId()));
        self::assertSame('auth_abc123', $authorization->processorAuthId());
        self::assertSame(5_000, $authorization->amount()->amountInMinorUnits);
        self::assertSame($merchant, $authorization->merchant());
        self::assertEquals($requestedAt, $authorization->requestedAt());
        self::assertEquals($decidedAt, $authorization->decidedAt());
        self::assertNull($authorization->reversedAt());
    }

    private function anApprovedAuthorization(): Authorization
    {
        return Authorization::record(
            AuthorizationId::generate(),
            CardId::generate(),
            'auth_abc123',
            Money::usd(5_000),
            $this->aMerchant(),
            AuthorizationResult::approved(),
            $this->at('2026-05-14T12:34:56Z'),
            $this->at('2026-05-14T12:34:56Z'),
        );
    }

    private function aMerchant(): Merchant
    {
        return new Merchant('Uber', MerchantCategoryCode::rideSharing());
    }

    private function at(string $iso8601Utc): \DateTimeImmutable
    {
        return new \DateTimeImmutable($iso8601Utc);
    }
}
