<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthorizationWebhookTest extends WebTestCase
{
    private const PROCESSOR_SECRET = 'test_webhook_secret';
    private const ADMIN_KEY = 'test_admin_key';

    public function test_a_signed_request_against_an_active_card_is_approved_and_decrements_the_balance(): void
    {
        $client = static::createClient();
        $cardId = $this->issueAndActivateCard($client, balance: 100_00);

        $processorAuthId = 'auth_'.bin2hex(random_bytes(4));
        $body = $this->webhookBody($cardId, $processorAuthId, amount: 25_00);
        $client->request('POST', '/api/webhooks/authorization', server: $this->signedHeaders($body), content: $body);

        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);
        self::assertSame('approved', $payload['status']);
        self::assertNull($payload['decline_reason']);

        $card = $this->getCard($client, $cardId);
        self::assertSame(75_00, $card['available_balance']['amount']);
    }

    public function test_an_insufficient_funds_authorization_is_declined_with_200_status(): void
    {
        $client = static::createClient();
        $cardId = $this->issueAndActivateCard($client, balance: 10_00);

        $body = $this->webhookBody($cardId, 'auth_'.bin2hex(random_bytes(4)), amount: 50_00);
        $client->request('POST', '/api/webhooks/authorization', server: $this->signedHeaders($body), content: $body);

        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);
        self::assertSame('declined', $payload['status']);
        self::assertSame('INSUFFICIENT_FUNDS', $payload['decline_reason']);
    }

    public function test_a_replay_of_the_same_processor_auth_id_returns_the_cached_decision(): void
    {
        $client = static::createClient();
        $cardId = $this->issueAndActivateCard($client, balance: 100_00);

        $processorAuthId = 'auth_'.bin2hex(random_bytes(4));
        $body = $this->webhookBody($cardId, $processorAuthId, amount: 30_00);
        $headers = $this->signedHeaders($body);

        $client->request('POST', '/api/webhooks/authorization', server: $headers, content: $body);
        $first = $this->decode($client);

        // The second call uses a different body (a larger amount) but the
        // same processor_auth_id — the cache must short-circuit it.
        $replayBody = $this->webhookBody($cardId, $processorAuthId, amount: 95_00);
        $client->request('POST', '/api/webhooks/authorization', server: $this->signedHeaders($replayBody), content: $replayBody);
        $second = $this->decode($client);

        self::assertSame($first['authorization_id'], $second['authorization_id']);
        self::assertSame($first['status'], $second['status']);

        // Balance only debited once (the original 30_00).
        $card = $this->getCard($client, $cardId);
        self::assertSame(70_00, $card['available_balance']['amount']);
    }

    public function test_a_request_without_a_signature_header_is_rejected_with_401(): void
    {
        $client = static::createClient();
        $body = $this->webhookBody('018e7c8a-1d2b-7d3e-9abc-def012345678', 'auth_x', 100);

        $client->request('POST', '/api/webhooks/authorization', content: $body);

        self::assertSame(401, $client->getResponse()->getStatusCode());
        $payload = $this->decode($client);
        self::assertSame('INVALID_SIGNATURE', $payload['error']['code']);
    }

    public function test_a_request_with_a_wrong_signature_is_rejected_with_401(): void
    {
        $client = static::createClient();
        $body = $this->webhookBody('018e7c8a-1d2b-7d3e-9abc-def012345678', 'auth_x', 100);
        $timestamp = (string) time();
        $wrong = hash_hmac('sha256', $timestamp.'.different-body', self::PROCESSOR_SECRET);
        $headers = ['HTTP_X-Processor-Signature' => "t={$timestamp},v1={$wrong}"];

        $client->request('POST', '/api/webhooks/authorization', server: $headers, content: $body);

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Issues + activates a card via the admin API; returns its id.
     */
    private function issueAndActivateCard(KernelBrowser $client, int $balance): string
    {
        $cardholderId = '018e7c8a-1d2b-7d3e-9abc-'.bin2hex(random_bytes(6));
        $client->request(
            'POST',
            '/api/cards',
            server: $this->adminHeaders(),
            content: json_encode([
                'cardholder_id' => $cardholderId,
                'spending_limits' => ['per_transaction' => 100_00, 'daily' => 1_000_00, 'monthly' => 10_000_00],
                'initial_balance' => $balance,
                'currency' => 'USD',
                'allowed_merchant_categories' => ['4121', '5812'],
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $cardId = $this->decode($client)['id'];
        self::assertIsString($cardId);

        $client->request('POST', '/api/cards/'.$cardId.'/activate', server: $this->adminHeaders());
        self::assertSame(204, $client->getResponse()->getStatusCode());

        return $cardId;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCard(KernelBrowser $client, string $cardId): array
    {
        $client->request('GET', '/api/cards/'.$cardId, server: $this->adminHeaders());
        self::assertResponseIsSuccessful();

        return $this->decode($client);
    }

    private function webhookBody(string $cardId, string $processorAuthId, int $amount): string
    {
        return json_encode([
            'processor_auth_id' => $processorAuthId,
            'card_id' => $cardId,
            'amount' => $amount,
            'currency' => 'USD',
            'merchant' => ['name' => 'Uber', 'category_code' => '4121'],
            'requested_at' => '2026-05-14T12:34:56Z',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $body): array
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::PROCESSOR_SECRET);

        return ['HTTP_X-Processor-Signature' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'];
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
