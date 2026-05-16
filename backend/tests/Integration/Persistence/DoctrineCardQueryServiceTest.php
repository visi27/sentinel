<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Card\Card;
use App\Domain\Card\CardholderId;
use App\Domain\Card\SpendingLimits;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Infrastructure\Persistence\Doctrine\DoctrineCardRepository;
use App\Infrastructure\Persistence\Doctrine\Query\DoctrineCardQueryService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCardQueryServiceTest extends KernelTestCase
{
    public function test_find_card_view_projects_a_persisted_card_into_the_view_dto(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCardRepository($em);
        $card = Card::issue(
            CardholderId::generate(),
            new SpendingLimits(Money::usd(500_00), Money::usd(1_000_00), Money::usd(5_000_00)),
            Money::usd(1_000_00),
            [MerchantCategoryCode::rideSharing()],
            new \DateTimeImmutable('2026-04-01T10:00:00Z'),
        );
        $repository->save($card);
        $em->flush();

        $query = new DoctrineCardQueryService(self::getContainer()->get(Connection::class));
        $view = $query->findCardView($card->id());

        self::assertNotNull($view);
        self::assertSame($card->id()->toString(), $view->id);
        self::assertSame('pending', $view->status);
        self::assertSame(500_00, $view->spendingLimits->perTransaction->amount);
        self::assertSame('USD', $view->availableBalance->currency);
        self::assertSame(['4121'], $view->allowedMerchantCategories);
    }

    public function test_find_card_view_returns_null_when_the_row_is_missing(): void
    {
        self::bootKernel();
        $query = new DoctrineCardQueryService(self::getContainer()->get(Connection::class));

        self::assertNull($query->findCardView(\App\Domain\Card\CardId::generate()));
    }
}
