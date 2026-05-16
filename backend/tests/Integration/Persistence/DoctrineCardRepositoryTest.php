<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\CardStatus;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Infrastructure\Persistence\Doctrine\DoctrineCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCardRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineCardRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = new DoctrineCardRepository($this->entityManager);
    }

    public function test_a_card_round_trips_through_persistence_with_every_field_intact(): void
    {
        $card = $this->newCard();
        $expectedId = $card->id();
        $expectedCardholderId = $card->cardholderId();

        $this->repository->save($card);
        $this->entityManager->flush();
        // clear() forces the next find to round-trip through the database
        // instead of returning the same object from the identity map.
        $this->entityManager->clear();

        $loaded = $this->repository->findById($expectedId);

        self::assertNotNull($loaded);
        self::assertTrue($expectedId->equals($loaded->id()));
        self::assertTrue($expectedCardholderId->equals($loaded->cardholderId()));
        self::assertSame(CardStatus::Pending, $loaded->status());
        self::assertSame(500_00, $loaded->spendingLimits()->perTransaction->amountInMinorUnits);
        self::assertSame(1_000_00, $loaded->availableBalance()->amountInMinorUnits);
        self::assertTrue($loaded->dailySpend()->isZero());
        self::assertCount(2, $loaded->allowedMerchantCategoryCodes());
        self::assertSame('4121', $loaded->allowedMerchantCategoryCodes()[0]->code);
    }

    public function test_aggregate_state_changes_persist_through_a_subsequent_save(): void
    {
        $card = $this->newCard();
        $this->repository->save($card);
        $this->entityManager->flush();

        $card->activate(new \DateTimeImmutable('2026-04-01T10:30:00Z'));
        $this->repository->save($card);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findById($card->id());
        self::assertNotNull($loaded);
        self::assertSame(CardStatus::Active, $loaded->status());
        self::assertNotNull($loaded->activatedAt());
    }

    public function test_find_by_id_returns_null_when_no_row_matches(): void
    {
        $missing = \App\Domain\Card\CardId::generate();

        self::assertNull($this->repository->findById($missing));
    }

    private function newCard(): Card
    {
        return Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(500_00), Money::usd(1_000_00), Money::usd(5_000_00)),
            Money::usd(1_000_00),
            [MerchantCategoryCode::rideSharing(), MerchantCategoryCode::restaurants()],
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );
    }
}
