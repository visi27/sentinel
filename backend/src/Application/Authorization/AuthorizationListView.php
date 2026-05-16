<?php

declare(strict_types=1);

namespace App\Application\Authorization;

/**
 * A page of AuthorizationView rows plus the metadata a client needs to
 * navigate further pages.
 */
final class AuthorizationListView implements \JsonSerializable
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

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total_items' => $this->totalItems,
            'total_pages' => $this->totalPages(),
        ];
    }
}
