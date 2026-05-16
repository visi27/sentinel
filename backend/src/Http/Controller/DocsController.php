<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the hand-written OpenAPI spec and the Scalar-rendered interactive
 * console. Both endpoints are read-only and the spec contains no secrets.
 */
final class DocsController
{
    public function __construct(private readonly string $projectDir)
    {
    }

    #[Route('/openapi.yaml', name: 'docs_openapi', methods: ['GET'])]
    public function spec(): Response
    {
        return new Response(
            (string) file_get_contents($this->projectDir.'/openapi.yaml'),
            200,
            ['Content-Type' => 'application/yaml; charset=utf-8'],
        );
    }

    #[Route('/docs', name: 'docs_ui', methods: ['GET'])]
    public function ui(): Response
    {
        return new Response(
            (string) file_get_contents($this->projectDir.'/src/Http/Resources/api-docs.html'),
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
