<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Authorization\ListAuthorizationsForCardQuery;
use App\Application\Authorization\ListAuthorizationsForCardQueryHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ListAuthorizationsController
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ListAuthorizationsForCardQueryHandler $handler)
    {
    }

    #[Route('/api/cards/{id}/authorizations', name: 'cards_authorizations_list', methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query->get('per_page', '25')));

        $list = ($this->handler)(new ListAuthorizationsForCardQuery($id, $page, $perPage));

        return new JsonResponse($list);
    }
}
