<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Authorization\Event\AuthorizationReversed;
use App\Domain\Authorization\Event\CardAuthorizationApproved;
use App\Domain\Authorization\Event\CardAuthorizationDeclined;
use App\Domain\Card\Event\CardActivated;
use App\Domain\Card\Event\CardClosed;
use App\Domain\Card\Event\CardIssued;
use App\Domain\Card\Event\CardSuspended;
use App\Domain\Card\Event\SpendingLimitsChanged;
use App\Domain\Shared\DomainEvent;

/**
 * Translates domain event classes to the stable wire-format event type
 * strings used by subscribers (see config/subscribers.yaml in Phase 6).
 * Keeping this map in infrastructure means the domain interface stays
 * minimal — events themselves do not need to know their wire name.
 */
final class DomainEventTypeMap
{
    /** @var array<class-string<DomainEvent>, string> */
    private const TYPES = [
        CardIssued::class => 'card.issued',
        CardActivated::class => 'card.activated',
        CardSuspended::class => 'card.suspended',
        CardClosed::class => 'card.closed',
        SpendingLimitsChanged::class => 'card.spending_limits_changed',
        CardAuthorizationApproved::class => 'card.authorization.approved',
        CardAuthorizationDeclined::class => 'card.authorization.declined',
        AuthorizationReversed::class => 'authorization.reversed',
    ];

    private function __construct()
    {
    }

    public static function typeFor(DomainEvent $event): string
    {
        $class = $event::class;

        return self::TYPES[$class]
            ?? throw new \LogicException(sprintf('No wire-format type registered for %s.', $class));
    }
}
