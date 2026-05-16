<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Card\GetCardQuery;
use App\Application\Card\GetCardQueryHandler;
use App\Domain\Card\CardId;
use App\Domain\Card\Exception\CardNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetCardController
{
    public function __construct(private readonly GetCardQueryHandler $handler)
    {
    }

    #[Route('/api/cards/{id}', name: 'cards_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $view = ($this->handler)(new GetCardQuery($id));
        if (null === $view) {
            // Translates to 404 via the exception subscriber. Throwing here
            // means the controller does not have to know about transport-
            // level status codes.
            throw CardNotFoundException::withId(CardId::fromString($id));
        }

        return new JsonResponse($view);
    }
}
