<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Authorization\Authorization;

/**
 * What the authorization webhook handler returns to the processor. Compact
 * on purpose: the processor needs the decision and (when declined) the
 * reason — nothing else.
 */
final class AuthorizationDecisionDto implements \JsonSerializable
{
    public function __construct(
        public readonly string $authorizationId,
        public readonly string $status,
        public readonly ?string $declineReason,
    ) {
    }

    public static function fromAuthorization(Authorization $authorization): self
    {
        return new self(
            $authorization->id()->toString(),
            $authorization->status()->value,
            $authorization->declineReason()?->value,
        );
    }

    /**
     * @return array{authorization_id: string, status: string, decline_reason: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'authorization_id' => $this->authorizationId,
            'status' => $this->status,
            'decline_reason' => $this->declineReason,
        ];
    }
}
