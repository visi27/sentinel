<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fakes;

use App\Domain\Authorization\Authorization;
use App\Domain\Authorization\AuthorizationRepository;

final class InMemoryAuthorizationRepository implements AuthorizationRepository
{
    /** @var array<string, Authorization> */
    private array $authorizations = [];

    public function findByProcessorAuthId(string $processorAuthId): ?Authorization
    {
        foreach ($this->authorizations as $authorization) {
            if ($authorization->processorAuthId() === $processorAuthId) {
                return $authorization;
            }
        }

        return null;
    }

    public function save(Authorization $authorization): void
    {
        $this->authorizations[$authorization->id()->toString()] = $authorization;
    }

    /**
     * @return list<Authorization>
     */
    public function all(): array
    {
        return array_values($this->authorizations);
    }
}
