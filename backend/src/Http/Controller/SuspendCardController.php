<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Card\SuspendCardCommandHandler;
use App\Http\Request\SuspendCardRequestParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SuspendCardController
{
    public function __construct(
        private readonly SuspendCardRequestParser $parser,
        private readonly SuspendCardCommandHandler $handler,
    ) {
    }

    #[Route('/api/cards/{id}/suspend', name: 'cards_suspend', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        ($this->handler)($this->parser->parse($request, $id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
