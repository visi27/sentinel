<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Card\ActivateCardCommand;
use App\Application\Card\ActivateCardCommandHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ActivateCardController
{
    public function __construct(private readonly ActivateCardCommandHandler $handler)
    {
    }

    #[Route('/api/cards/{id}/activate', name: 'cards_activate', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        ($this->handler)(new ActivateCardCommand($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
