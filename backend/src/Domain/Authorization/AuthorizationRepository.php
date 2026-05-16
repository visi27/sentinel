<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

/**
 * Persistence port for the Authorization aggregate. findByProcessorAuthId is
 * the application-layer backstop for webhook idempotency; a unique DB
 * constraint on processor_auth_id provides the hard guarantee.
 */
interface AuthorizationRepository
{
    public function findByProcessorAuthId(string $processorAuthId): ?Authorization;

    public function save(Authorization $authorization): void;
}
