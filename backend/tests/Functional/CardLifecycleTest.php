<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CardLifecycleTest extends WebTestCase
{
    private const ADMIN_KEY = 'test_admin_key';

    public function test_a_card_can_be_issued_activated_inspected_and_suspended(): void
    {
        $client = static::createClient();

        // Issue
        $client->request('POST', '/api/cards', server: $this->adminHeaders(), content: json_encode([
            'cardholder_id' => '018e7c8a-1d2b-7d3e-9abc-aaa000000001',
            'spending_limits' => ['per_transaction' => 50_00, 'daily' => 100_00, 'monthly' => 500_00],
            'initial_balance' => 1_000_00,
            'currency' => 'USD',
            'allowed_merchant_categories' => ['4121'],
        ], JSON_THROW_ON_ERROR));
        self::assertSame(201, $client->getResponse()->getStatusCode());
        self::assertNotNull($client->getResponse()->headers->get('Location'));
        $cardId = $this->decode($client)['id'];
        self::assertIsString($cardId);

        // Activate
        $client->request('POST', '/api/cards/'.$cardId.'/activate', server: $this->adminHeaders());
        self::assertSame(204, $client->getResponse()->getStatusCode());

        // Inspect
        $client->request('GET', '/api/cards/'.$cardId, server: $this->adminHeaders());
        self::assertResponseIsSuccessful();
        $card = $this->decode($client);
        self::assertSame('active', $card['status']);
        self::assertSame(1_000_00, $card['available_balance']['amount']);
        self::assertSame('USD', $card['available_balance']['currency']);
        self::assertNotNull($card['activated_at']);

        // Suspend
        $client->request('POST', '/api/cards/'.$cardId.'/suspend', server: $this->adminHeaders(), content: json_encode([
            'reason' => 'Lost card reported',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/cards/'.$cardId, server: $this->adminHeaders());
        self::assertSame('suspended', $this->decode($client)['status']);
    }

    public function test_get_card_returns_404_envelope_for_an_unknown_card(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/cards/018e7c8a-1d2b-7d3e-9abc-aaa999999999', server: $this->adminHeaders());

        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame('CARD_NOT_FOUND', $this->decode($client)['error']['code']);
    }

    public function test_activate_returns_409_envelope_when_the_aggregate_rejects_the_transition(): void
    {
        $client = static::createClient();
        // Issue a card, activate it, then attempt to activate again — the
        // aggregate rejects double-activation.
        $client->request('POST', '/api/cards', server: $this->adminHeaders(), content: json_encode([
            'cardholder_id' => '018e7c8a-1d2b-7d3e-9abc-aaa000000002',
            'spending_limits' => ['per_transaction' => 50_00, 'daily' => 100_00, 'monthly' => 500_00],
            'initial_balance' => 1_000_00,
            'currency' => 'USD',
            'allowed_merchant_categories' => ['4121'],
        ], JSON_THROW_ON_ERROR));
        $cardId = $this->decode($client)['id'];
        self::assertIsString($cardId);

        $client->request('POST', '/api/cards/'.$cardId.'/activate', server: $this->adminHeaders());
        self::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/cards/'.$cardId.'/activate', server: $this->adminHeaders());
        self::assertSame(409, $client->getResponse()->getStatusCode());
        self::assertSame('INVALID_STATE_TRANSITION', $this->decode($client)['error']['code']);
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return ['HTTP_X-API-Key' => self::ADMIN_KEY, 'CONTENT_TYPE' => 'application/json'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        $content = (string) $client->getResponse()->getContent();
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }
}
