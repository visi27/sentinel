<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Application\Outbox\OutboxRepository;
use App\Application\Shared\Clock;
use App\Application\Shared\TransactionManager;
use App\Domain\Authorization\Authorization;
use App\Domain\Authorization\AuthorizationId;
use App\Domain\Authorization\AuthorizationRepository;
use App\Domain\Authorization\DeclineReason;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\Card;
use App\Domain\Card\CardId;
use App\Domain\Card\CardRepository;
use App\Domain\Merchant\GeoLocation;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Currency;
use App\Domain\Money\Money;

/**
 * Orchestrates the inbound authorization flow: idempotency check, aggregate
 * load, domain decision, atomic persistence + outbox.
 *
 * Has no business logic of its own — every rule is on the Card aggregate.
 */
final class AuthorizeCardCommandHandler
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly OutboxRepository $outboxRepository,
        private readonly TransactionManager $transactionManager,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(AuthorizeCardCommand $command): AuthorizationDecisionDto
    {
        // Application-layer idempotency: if we already decided this
        // processor_auth_id, return the prior outcome unchanged. The unique
        // index on the column is the durable backstop.
        $existing = $this->authorizationRepository->findByProcessorAuthId($command->processorAuthId);
        if (null !== $existing) {
            return AuthorizationDecisionDto::fromAuthorization($existing);
        }

        $cardId = CardId::fromString($command->cardId);
        $card = $this->cardRepository->findById($cardId);
        $amount = new Money($command->amount, Currency::from($command->currency));
        $merchant = $this->buildMerchant($command);

        if (null === $card) {
            // The processor referenced a card we don't have. We still record
            // the attempt for audit and reply CARD_NOT_ACTIVE — the processor
            // treats absent and inactive the same way.
            return $this->persistOutcome(
                $command,
                $cardId,
                $amount,
                $merchant,
                AuthorizationResult::declined(DeclineReason::CardNotActive),
                null,
            );
        }

        $result = $card->authorize($amount, $merchant, $command->requestedAt);

        return $this->persistOutcome($command, $card->id(), $amount, $merchant, $result, $card);
    }

    private function buildMerchant(AuthorizeCardCommand $command): Merchant
    {
        $location = null === $command->merchantLocation ? null : new GeoLocation(
            $command->merchantLocation->city,
            $command->merchantLocation->region,
            $command->merchantLocation->country,
        );

        return new Merchant(
            $command->merchantName,
            MerchantCategoryCode::ofCode($command->merchantCategoryCode),
            $location,
        );
    }

    private function persistOutcome(
        AuthorizeCardCommand $command,
        CardId $cardId,
        Money $amount,
        Merchant $merchant,
        AuthorizationResult $result,
        ?Card $card,
    ): AuthorizationDecisionDto {
        $authorization = Authorization::record(
            AuthorizationId::generate(),
            $cardId,
            $command->processorAuthId,
            $amount,
            $merchant,
            $result,
            $command->requestedAt,
            $this->clock->now(),
        );

        $this->transactionManager->run(function () use ($card, $authorization, $result): void {
            // Only an approved authorization mutated the card; declines are
            // pure reads and don't need a write.
            if (null !== $card && $result->isApproved) {
                $this->cardRepository->save($card);
            }

            $this->authorizationRepository->save($authorization);

            $events = null === $card
                ? $authorization->releaseEvents()
                : array_merge($card->releaseEvents(), $authorization->releaseEvents());

            foreach ($events as $event) {
                $this->outboxRepository->store($event);
            }
        });

        return AuthorizationDecisionDto::fromAuthorization($authorization);
    }
}
