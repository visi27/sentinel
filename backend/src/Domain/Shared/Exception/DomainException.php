<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

/**
 * Base type for every exception raised by the domain layer. Application code
 * catches this (or its subtypes) and translates business-rule violations into
 * transport-level responses.
 */
abstract class DomainException extends \RuntimeException
{
}
