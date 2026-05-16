<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Webhook;

use App\Infrastructure\Webhook\Exception\InvalidWebhookSignatureException;
use App\Infrastructure\Webhook\ProcessorWebhookSignatureVerifier;
use App\Tests\Unit\Application\Fakes\FixedClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProcessorWebhookSignatureVerifierTest extends TestCase
{
    private const SECRET = 'test_shared_secret';

    public function test_accepts_a_correctly_signed_request(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z'));
        $verifier = new ProcessorWebhookSignatureVerifier(self::SECRET, $clock);
        $body = '{"hello":"world"}';
        $timestamp = $clock->now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);
        $request = $this->makeRequest($body, "t={$timestamp},v1={$signature}");

        $verifier->verify($request);

        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_a_missing_header(): void
    {
        $verifier = new ProcessorWebhookSignatureVerifier(
            self::SECRET,
            new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z')),
        );

        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Missing X-Processor-Signature header.');

        $verifier->verify($this->makeRequest('body', null));
    }

    public function test_rejects_a_malformed_header(): void
    {
        $verifier = new ProcessorWebhookSignatureVerifier(
            self::SECRET,
            new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z')),
        );

        $this->expectException(InvalidWebhookSignatureException::class);

        $verifier->verify($this->makeRequest('body', 'garbage-no-equals'));
    }

    public function test_rejects_a_non_numeric_timestamp(): void
    {
        $verifier = new ProcessorWebhookSignatureVerifier(
            self::SECRET,
            new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z')),
        );

        $this->expectException(InvalidWebhookSignatureException::class);

        $verifier->verify($this->makeRequest('body', 't=notanumber,v1=abcd'));
    }

    public function test_rejects_a_stale_timestamp(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z'));
        $verifier = new ProcessorWebhookSignatureVerifier(self::SECRET, $clock);
        // 6 minutes before "now" → outside the 5-minute tolerance.
        $stale = $clock->now()->getTimestamp() - 6 * 60;
        $signature = hash_hmac('sha256', $stale.'.body', self::SECRET);

        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Signature timestamp is outside the tolerance window.');

        $verifier->verify($this->makeRequest('body', "t={$stale},v1={$signature}"));
    }

    public function test_rejects_a_future_timestamp_outside_the_window(): void
    {
        // Symmetry check: clock skew the other way is also rejected.
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z'));
        $verifier = new ProcessorWebhookSignatureVerifier(self::SECRET, $clock);
        $future = $clock->now()->getTimestamp() + 6 * 60;
        $signature = hash_hmac('sha256', $future.'.body', self::SECRET);

        $this->expectException(InvalidWebhookSignatureException::class);

        $verifier->verify($this->makeRequest('body', "t={$future},v1={$signature}"));
    }

    public function test_rejects_a_signature_that_does_not_match_the_body(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z'));
        $verifier = new ProcessorWebhookSignatureVerifier(self::SECRET, $clock);
        $timestamp = $clock->now()->getTimestamp();
        // Signature was computed over a *different* body.
        $signature = hash_hmac('sha256', $timestamp.'.different', self::SECRET);

        $this->expectException(InvalidWebhookSignatureException::class);
        $this->expectExceptionMessage('Signature did not match the request body.');

        $verifier->verify($this->makeRequest('actual body', "t={$timestamp},v1={$signature}"));
    }

    public function test_rejects_a_signature_computed_with_the_wrong_secret(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-14T12:34:56Z'));
        $verifier = new ProcessorWebhookSignatureVerifier(self::SECRET, $clock);
        $timestamp = $clock->now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.body', 'wrong_secret');

        $this->expectException(InvalidWebhookSignatureException::class);

        $verifier->verify($this->makeRequest('body', "t={$timestamp},v1={$signature}"));
    }

    private function makeRequest(string $body, ?string $signatureHeader): Request
    {
        $headers = null !== $signatureHeader ? ['X-Processor-Signature' => $signatureHeader] : [];
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/api/webhooks/authorization', 'POST', [], [], [], $server, $body);
    }
}
