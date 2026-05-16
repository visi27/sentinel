<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Application\Outbox\OutboxRepository;
use App\Application\Shared\Clock;
use App\Application\Shared\TransactionManager;
use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardRepository;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Currency;
use App\Domain\Money\Money;

final class IssueCardCommandHandler
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly OutboxRepository $outboxRepository,
        private readonly TransactionManager $transactionManager,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(IssueCardCommand $command): string
    {
        $currency = Currency::from($command->currency);

        $limits = new SpendingLimits(
            new Money($command->perTransactionLimit, $currency),
            new Money($command->dailyLimit, $currency),
            new Money($command->monthlyLimit, $currency),
        );

        $allowedCategories = array_map(
            static fn (string $code): MerchantCategoryCode => MerchantCategoryCode::ofCode($code),
            $command->allowedMerchantCategoryCodes,
        );

        $card = Card::issue(
            CardholderId::fromString($command->cardholderId),
            $limits,
            new Money($command->initialBalance, $currency),
            $allowedCategories,
            $this->clock->now(),
        );

        $this->transactionManager->run(function () use ($card): void {
            $this->cardRepository->save($card);

            foreach ($card->releaseEvents() as $event) {
                $this->outboxRepository->store($event);
            }
        });

        return $card->id()->toString();
    }
}
