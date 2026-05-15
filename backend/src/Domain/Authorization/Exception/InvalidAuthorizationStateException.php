<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exception;

use App\Domain\Authorization\AuthorizationStatus;
use App\Domain\Shared\Exception\DomainException;

final class InvalidAuthorizationStateException extends DomainException
{
    public static function cannotReverse(AuthorizationStatus $status): self
    {
        return new self(sprintf(
            'Only an approved authorization can be reversed; this one is %s.',
            $status->value,
        ));
    }
}
