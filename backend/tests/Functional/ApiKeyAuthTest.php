<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiKeyAuthTest extends WebTestCase
{
    public function test_get_card_without_an_api_key_returns_401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/cards/018e7c8a-1d2b-7d3e-9abc-aaa000000099');

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function test_get_card_with_a_wrong_api_key_returns_401(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/cards/018e7c8a-1d2b-7d3e-9abc-aaa000000099',
            server: ['HTTP_X-API-Key' => 'not-a-real-key'],
        );

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function test_post_with_the_readonly_key_is_forbidden(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/cards',
            server: ['HTTP_X-API-Key' => 'test_readonly_key', 'CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function test_get_with_the_readonly_key_is_allowed(): void
    {
        $client = static::createClient();
        // The card doesn't exist; the point of this test is that auth
        // succeeds (not 401 / 403) and the request reaches the controller.
        $client->request(
            'GET',
            '/api/cards/018e7c8a-1d2b-7d3e-9abc-aaa000000099',
            server: ['HTTP_X-API-Key' => 'test_readonly_key'],
        );

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }
}
