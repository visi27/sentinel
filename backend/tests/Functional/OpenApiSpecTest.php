<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Shared\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Drift guard for the hand-written OpenAPI spec. Catches the cheap class
 * of bugs (renamed field, missing endpoint, response shape divergence)
 * without trying to be a full schema validator.
 *
 * What this test does NOT check:
 *  - That every request validates against the schema (would need
 *    justinrainbow/json-schema or similar).
 *  - That every response example is byte-identical to what the
 *    controller returns at runtime.
 *
 * What it DOES check:
 *  - The spec is reachable at /openapi.yaml and parses as YAML.
 *  - The /docs UI is reachable.
 *  - Every route exposed by the kernel is documented.
 *  - The IssueCard 201 response shape (the most copy-pasted example
 *    in the README) matches what the controller actually returns.
 */
final class OpenApiSpecTest extends WebTestCase
{
    public function test_openapi_spec_is_served_and_parses(): void
    {
        $client = static::createClient();
        $client->request('GET', '/openapi.yaml');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(
            'application/yaml',
            $client->getResponse()->headers->get('Content-Type') ?? '',
        );

        $spec = Yaml::parse((string) $client->getResponse()->getContent());

        self::assertIsArray($spec);
        self::assertSame('3.1.0', $spec['openapi'] ?? null);
        self::assertSame('Sentinel', $spec['info']['title'] ?? null);
    }

    public function test_docs_ui_renders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('@scalar/api-reference', $body);
        self::assertStringContainsString('/openapi.yaml', $body);
    }

    public function test_every_routed_endpoint_is_documented(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get('router');

        $client->request('GET', '/openapi.yaml');
        $spec = Yaml::parse((string) $client->getResponse()->getContent());
        $documented = array_keys($spec['paths'] ?? []);

        $undocumented = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            // Skip framework + docs routes — only the public API surface
            // needs to be in the spec.
            if (str_starts_with($path, '/_') || '/docs' === $path || '/openapi.yaml' === $path) {
                continue;
            }
            // Normalise Symfony's {id} placeholder for matching.
            if (!in_array($path, $documented, true)) {
                $undocumented[] = $name.' ['.$path.']';
            }
        }

        self::assertSame([], $undocumented, 'Undocumented endpoints found in the routing table.');
    }

    public function test_issue_card_response_matches_documented_schema(): void
    {
        $client = static::createClient();
        $client->request('GET', '/openapi.yaml');
        $spec = Yaml::parse((string) $client->getResponse()->getContent());

        $schema = $spec['components']['schemas']['IssueCardResponse'] ?? null;
        self::assertIsArray($schema);
        self::assertSame(['id'], $schema['required']);
        self::assertSame('uuid', $schema['properties']['id']['format'] ?? null);

        $client->request(
            'POST',
            '/api/cards',
            server: ['HTTP_X-API-Key' => $_ENV['ADMIN_API_KEY'] ?? 'test_admin_key'],
            content: json_encode([
                'cardholder_id' => Uuid::v7(),
                'spending_limits' => [
                    'per_transaction' => 50000,
                    'daily' => 200000,
                    'monthly' => 1000000,
                ],
                'initial_balance' => 100000,
                'currency' => 'USD',
                'allowed_merchant_categories' => ['4121', '5812'],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $body, 'Response must contain the documented "id" field.');
        self::assertIsString($body['id']);
    }
}
