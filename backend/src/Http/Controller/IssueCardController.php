<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Card\IssueCardCommandHandler;
use App\Http\Request\IssueCardRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IssueCardController
{
    public function __construct(
        private readonly IssueCardRequestParser $parser,
        private readonly IssueCardCommandHandler $handler,
    ) {
    }

    #[Route('/api/cards', name: 'cards_issue', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $cardId = ($this->handler)($this->parser->parse($request));

        return new JsonResponse(
            ['id' => $cardId],
            Response::HTTP_CREATED,
            ['Location' => '/api/cards/'.$cardId],
        );
    }
}
