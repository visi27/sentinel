<?php

declare(strict_types=1);

namespace App\Application\Authorization;

/**
 * A page of AuthorizationView rows plus the metadata a client needs to
 * navigate further pages.
 */
final class AuthorizationListView
{
    /**
     * @param list<AuthorizationView> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalItems,
    ) {
    }

    public function totalPages(): int
    {
        if (0 === $this->perPage) {
            return 0;
        }

        return (int) ceil($this->totalItems / $this->perPage);
    }
}
