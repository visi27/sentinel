<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Authorization\Authorization;
use App\Domain\Authorization\AuthorizationId;
use App\Domain\Authorization\AuthorizationStatus;
use App\Domain\Authorization\DeclineReason;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardId;
use App\Domain\Merchant\GeoLocation;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use App\Domain\Money\Money;
use App\Infrastructure\Persistence\Doctrine\DoctrineAuthorizationRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineAuthorizationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineAuthorizationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = new DoctrineAuthorizationRepository($this->entityManager);
    }

    public function test_an_authorization_round_trips_with_merchant_json_and_decline_reason(): void
    {
        $declined = Authorization::record(
            AuthorizationId::generate(),
            CardId::generate(),
            'auth_abc',
            Money::usd(2_500),
            new Merchant('Uber', MerchantCategoryCode::rideSharing(), new GeoLocation('Boston', 'MA', 'US')),
            AuthorizationResult::declined(DeclineReason::InsufficientFunds),
            new \DateTimeImmutable('2026-05-14T12:34:56Z'),
            new \DateTimeImmutable('2026-05-14T12:34:56Z'),
        );

        $this->repository->save($declined);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findByProcessorAuthId('auth_abc');
        self::assertNotNull($loaded);
        self::assertSame(AuthorizationStatus::Declined, $loaded->status());
        self::assertSame(DeclineReason::InsufficientFunds, $loaded->declineReason());
        self::assertSame('Uber', $loaded->merchant()->name);
        self::assertNotNull($loaded->merchant()->location);
        self::assertSame('Boston', $loaded->merchant()->location->city);
    }

    public function test_unique_constraint_rejects_a_duplicate_processor_auth_id(): void
    {
        $first = $this->approved('auth_dup');
        $this->repository->save($first);
        $this->entityManager->flush();

        $second = $this->approved('auth_dup');
        $this->repository->save($second);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    private function approved(string $processorAuthId): Authorization
    {
        return Authorization::record(
            AuthorizationId::generate(),
            CardId::generate(),
            $processorAuthId,
            Money::usd(1_000),
            new Merchant('Uber', MerchantCategoryCode::rideSharing()),
            AuthorizationResult::approved(),
            new \DateTimeImmutable('2026-05-14T12:34:56Z'),
            new \DateTimeImmutable('2026-05-14T12:34:56Z'),
        );
    }
}
