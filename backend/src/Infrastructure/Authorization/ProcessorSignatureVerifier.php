<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Application\Shared\Clock;
use App\Infrastructure\Authorization\Exception\InvalidProcessorSignatureException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies the HMAC-SHA256 signature the card processor attaches to its
 * inbound authorization request. The header format is
 * "t=<unix>,v1=<hex-hmac>", signing the canonical message
 * "<unix>.<request-body>".
 *
 * The 5-minute tolerance window protects against captured-request replay.
 */
final class ProcessorSignatureVerifier
{
    private const HEADER_NAME = 'X-Processor-Signature';
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    public function __construct(
        #[\SensitiveParameter] private readonly string $sharedSecret,
        private readonly Clock $clock,
        private readonly int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ) {
    }

    public function verify(Request $request): void
    {
        $header = $request->headers->get(self::HEADER_NAME);
        if (null === $header) {
            throw InvalidProcessorSignatureException::missingHeader();
        }

        [$timestamp, $signature] = $this->parseHeader($header);

        $age = $this->clock->now()->getTimestamp() - $timestamp;
        if ($age > $this->toleranceSeconds || $age < -$this->toleranceSeconds) {
            throw InvalidProcessorSignatureException::staleTimestamp();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $this->sharedSecret);

        // hash_equals is the only way to compare HMACs safely — equality on
        // strings reveals the prefix on mismatch via timing.
        if (!hash_equals($expected, $signature)) {
            throw InvalidProcessorSignatureException::mismatch();
        }
    }

    /**
     * @return array{int, string}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $segment) {
            $parts = explode('=', trim($segment), 2);
            if (2 !== count($parts)) {
                continue;
            }

            [$key, $value] = $parts;
            if ('t' === $key) {
                $timestamp = $value;
            } elseif ('v1' === $key) {
                $signature = $value;
            }
        }

        if (null === $timestamp || null === $signature || '' === $timestamp || '' === $signature) {
            throw InvalidProcessorSignatureException::malformedHeader();
        }

        if (1 !== preg_match('/^\d+$/', $timestamp)) {
            throw InvalidProcessorSignatureException::malformedHeader();
        }

        return [(int) $timestamp, $signature];
    }
}
