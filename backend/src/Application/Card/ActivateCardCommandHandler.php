<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Application\Outbox\OutboxRepository;
use App\Application\Shared\Clock;
use App\Application\Shared\TransactionManager;
use App\Domain\Card\CardId;
use App\Domain\Card\CardRepository;
use App\Domain\Card\Exception\CardNotFoundException;

final class ActivateCardCommandHandler
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly OutboxRepository $outboxRepository,
        private readonly TransactionManager $transactionManager,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ActivateCardCommand $command): void
    {
        $cardId = CardId::fromString($command->cardId);
        $card = $this->cardRepository->findById($cardId);

        if (null === $card) {
            throw CardNotFoundException::withId($cardId);
        }

        $card->activate($this->clock->now());

        $this->transactionManager->run(function () use ($card): void {
            $this->cardRepository->save($card);

            foreach ($card->releaseEvents() as $event) {
                $this->outboxRepository->store($event);
            }
        });
    }
}
