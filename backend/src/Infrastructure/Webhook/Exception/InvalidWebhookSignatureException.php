<?php

declare(strict_types=1);

namespace App\Infrastructure\Webhook\Exception;

/**
 * Raised when a processor webhook fails signature verification. Extends
 * RuntimeException rather than DomainException — signature failure is a
 * transport-layer concern, not a domain rule violation.
 */
final class InvalidWebhookSignatureException extends \RuntimeException
{
    public static function missingHeader(): self
    {
        return new self('Missing X-Processor-Signature header.');
    }

    public static function malformedHeader(): self
    {
        return new self('X-Processor-Signature header is malformed; expected "t=<unix>,v1=<hmac>".');
    }

    public static function staleTimestamp(): self
    {
        return new self('Signature timestamp is outside the tolerance window.');
    }

    public static function mismatch(): self
    {
        return new self('Signature did not match the request body.');
    }
}
