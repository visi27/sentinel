<?php

declare(strict_types=1);

namespace App\Http\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates admin REST endpoints by matching the X-API-Key header
 * against two configured keys: an admin key (ROLE_ADMIN) and a read-only
 * key (ROLE_READONLY). Comparison is timing-safe.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const HEADER_NAME = 'X-API-Key';

    public function __construct(
        #[\SensitiveParameter] private readonly string $adminKey,
        #[\SensitiveParameter] private readonly string $readonlyKey,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $supplied = (string) $request->headers->get(self::HEADER_NAME);
        if ('' === $supplied) {
            throw new CustomUserMessageAuthenticationException('Missing API key.');
        }

        if ('' !== $this->adminKey && hash_equals($this->adminKey, $supplied)) {
            return new SelfValidatingPassport(new UserBadge(
                'admin',
                static fn (): InMemoryUser => new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            ));
        }

        if ('' !== $this->readonlyKey && hash_equals($this->readonlyKey, $supplied)) {
            return new SelfValidatingPassport(new UserBadge(
                'readonly',
                static fn (): InMemoryUser => new InMemoryUser('readonly', null, ['ROLE_READONLY']),
            ));
        }

        throw new CustomUserMessageAuthenticationException('Invalid API key.');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to the controller.
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $exception->getMessage(),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
