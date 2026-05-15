<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Card;

use App\Domain\Authorization\DeclineReason;
use App\Domain\Card\AuthorizationResult;
use PHPUnit\Framework\TestCase;

final class AuthorizationResultTest extends TestCase
{
    public function test_approved_has_no_decline_reason(): void
    {
        $result = AuthorizationResult::approved();

        self::assertTrue($result->isApproved);
        self::assertNull($result->declineReason);
    }

    public function test_declined_carries_the_reason(): void
    {
        $result = AuthorizationResult::declined(DeclineReason::InsufficientFunds);

        self::assertFalse($result->isApproved);
        self::assertSame(DeclineReason::InsufficientFunds, $result->declineReason);
    }
}
