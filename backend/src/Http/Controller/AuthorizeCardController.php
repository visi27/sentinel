<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Card\AuthorizeCardCommandHandler;
use App\Application\Idempotency\IdempotencyStore;
use App\Http\Exception\InvalidRequestException;
use App\Http\Request\AuthorizeCardRequestParser;
use App\Infrastructure\Authorization\ProcessorSignatureVerifier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The inbound authorization endpoint. Synchronous request/decision —
 * the processor expects a body back within the 200 ms budget, so it's
 * RPC-shaped despite the HMAC-style signing. Order matters: signature
 * first (cheap, fails fast), idempotency cache lookup next, then the
 * command handler. Caching the response shape after the handler runs
 * means the processor's retries see the exact same JSON the first
 * call produced.
 */
final class AuthorizeCardController
{
    public function __construct(
        private readonly ProcessorSignatureVerifier $signatureVerifier,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly AuthorizeCardRequestParser $parser,
        private readonly AuthorizeCardCommandHandler $handler,
    ) {
    }

    #[Route('/api/authorizations', name: 'authorizations_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $this->signatureVerifier->verify($request);

        $idempotencyKey = $this->extractIdempotencyKey($request);
        $cached = $this->idempotencyStore->retrieve($idempotencyKey);
        if (null !== $cached) {
            return JsonResponse::fromJsonString($cached);
        }

        $decision = ($this->handler)($this->parser->parse($request));
        $response = new JsonResponse($decision);
        $this->idempotencyStore->store($idempotencyKey, (string) $response->getContent());

        return $response;
    }

    private function extractIdempotencyKey(Request $request): string
    {
        $header = $request->headers->get('Idempotency-Key');
        if (is_string($header) && '' !== $header) {
            return $header;
        }

        // Fall back to the processor's auth id, which is durable across
        // retries by definition.
        $raw = $request->getContent();
        $decoded = '' === $raw ? null : json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['processor_auth_id']) && is_string($decoded['processor_auth_id'])) {
            return $decoded['processor_auth_id'];
        }

        throw InvalidRequestException::missingField('processor_auth_id (or Idempotency-Key header)');
    }
}
