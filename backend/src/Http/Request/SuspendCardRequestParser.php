<?php

declare(strict_types=1);

namespace App\Http\Request;

use App\Application\Card\SuspendCardCommand;
use Symfony\Component\HttpFoundation\Request;

final class SuspendCardRequestParser
{
    public function parse(Request $request, string $cardId): SuspendCardCommand
    {
        $body = JsonReader::decode($request);

        return new SuspendCardCommand(
            cardId: $cardId,
            reason: JsonReader::string($body, 'reason'),
        );
    }
}
