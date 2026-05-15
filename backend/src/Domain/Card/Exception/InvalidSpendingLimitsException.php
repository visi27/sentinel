<?php

declare(strict_types=1);

namespace App\Domain\Card\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidSpendingLimitsException extends DomainException
{
    public static function dailyBelowPerTransaction(): self
    {
        return new self('Daily spending limit cannot be lower than the per-transaction limit.');
    }

    public static function monthlyBelowDaily(): self
    {
        return new self('Monthly spending limit cannot be lower than the daily limit.');
    }
}
