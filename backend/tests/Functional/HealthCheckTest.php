<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthCheckTest extends WebTestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['status' => 'ok'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }
}
