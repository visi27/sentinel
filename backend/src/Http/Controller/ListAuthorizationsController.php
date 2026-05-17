<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Authorization\ListAuthorizationsForCardQuery;
use App\Application\Authorization\ListAuthorizationsForCardQueryHandler;
use App\Http\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ListAuthorizationsController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ListAuthorizationsForCardQueryHandler $handler)
    {
    }

    #[Route('/api/cards/{id}/authorizations', name: 'cards_authorizations_list', methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $page = $this->intParam($request, 'page', self::DEFAULT_PAGE, min: 1);
        $perPage = $this->intParam($request, 'per_page', self::DEFAULT_PER_PAGE, min: 1, max: self::MAX_PER_PAGE);

        $list = ($this->handler)(new ListAuthorizationsForCardQuery($id, $page, $perPage));

        return new JsonResponse($list);
    }

    private function intParam(Request $request, string $name, int $default, int $min, ?int $max = null): int
    {
        $raw = $request->query->get($name);
        if (null === $raw || '' === $raw) {
            return $default;
        }
        if (!ctype_digit($raw) && !(str_starts_with($raw, '-') && ctype_digit(substr($raw, 1)))) {
            throw InvalidRequestException::invalidValue($name, 'must be an integer');
        }
        $value = (int) $raw;
        if ($value < $min) {
            throw InvalidRequestException::invalidValue($name, sprintf('must be >= %d', $min));
        }
        if (null !== $max && $value > $max) {
            throw InvalidRequestException::invalidValue($name, sprintf('must be <= %d', $max));
        }

        return $value;
    }
}
