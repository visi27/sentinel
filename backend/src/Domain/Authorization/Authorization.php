<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Authorization\Event\AuthorizationReversed;
use App\Domain\Authorization\Event\CardAuthorizationApproved;
use App\Domain\Authorization\Event\CardAuthorizationDeclined;
use App\Domain\Authorization\Exception\InvalidAuthorizationStateException;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardId;
use App\Domain\Merchant\Merchant;
use App\Domain\Money\Money;
use App\Domain\Shared\AggregateRoot;

/**
 * The Authorization aggregate. It is created once a decision has been reached
 * for an incoming request and is essentially immutable afterwards — only a
 * reversal can change its state. It is separate from Card because its
 * lifecycle, transactional scope, and retention period differ.
 */
final class Authorization extends AggregateRoot
{
    private function __construct(
        private readonly AuthorizationId $id,
        private readonly CardId $cardId,
        private readonly string $processorAuthId,
        private readonly Money $amount,
        private readonly Merchant $merchant,
        private AuthorizationStatus $status,
        private readonly ?DeclineReason $declineReason,
        private readonly \DateTimeImmutable $requestedAt,
        private readonly ?\DateTimeImmutable $decidedAt,
        private ?\DateTimeImmutable $reversedAt,
    ) {
    }

    public static function record(
        AuthorizationId $id,
        CardId $cardId,
        string $processorAuthId,
        Money $amount,
        Merchant $merchant,
        AuthorizationResult $result,
        \DateTimeImmutable $requestedAt,
        \DateTimeImmutable $decidedAt,
    ): self {
        if ($result->isApproved) {
            $authorization = new self(
                $id,
                $cardId,
                $processorAuthId,
                $amount,
                $merchant,
                AuthorizationStatus::Approved,
                null,
                $requestedAt,
                $decidedAt,
                null,
            );
            $authorization->raise(new CardAuthorizationApproved(
                $cardId,
                $id,
                $amount,
                $merchant,
                $decidedAt,
            ));

            return $authorization;
        }

        $reason = $result->declineReason
            ?? throw new \LogicException('A declined AuthorizationResult must carry a decline reason.');

        $authorization = new self(
            $id,
            $cardId,
            $processorAuthId,
            $amount,
            $merchant,
            AuthorizationStatus::Declined,
            $reason,
            $requestedAt,
            $decidedAt,
            null,
        );
        $authorization->raise(new CardAuthorizationDeclined(
            $cardId,
            $id,
            $amount,
            $merchant,
            $reason,
            $decidedAt,
        ));

        return $authorization;
    }

    public function reverse(\DateTimeImmutable $reversedAt): void
    {
        if (AuthorizationStatus::Approved !== $this->status) {
            throw InvalidAuthorizationStateException::cannotReverse($this->status);
        }

        $this->status = AuthorizationStatus::Reversed;
        $this->reversedAt = $reversedAt;
        $this->raise(new AuthorizationReversed($this->id, $this->cardId, $reversedAt));
    }

    public function isApproved(): bool
    {
        return AuthorizationStatus::Approved === $this->status;
    }

    public function id(): AuthorizationId
    {
        return $this->id;
    }

    public function cardId(): CardId
    {
        return $this->cardId;
    }

    public function processorAuthId(): string
    {
        return $this->processorAuthId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function merchant(): Merchant
    {
        return $this->merchant;
    }

    public function status(): AuthorizationStatus
    {
        return $this->status;
    }

    public function declineReason(): ?DeclineReason
    {
        return $this->declineReason;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function decidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function reversedAt(): ?\DateTimeImmutable
    {
        return $this->reversedAt;
    }
}
