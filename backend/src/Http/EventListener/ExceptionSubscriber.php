<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use App\Domain\Authorization\Exception\InvalidAuthorizationStateException;
use App\Domain\Card\Exception\CardNotFoundException;
use App\Domain\Card\Exception\InvalidCardStateTransitionException;
use App\Domain\Card\Exception\InvalidSpendingLimitsException;
use App\Domain\Merchant\Exception\InvalidMerchantCategoryCodeException;
use App\Domain\Money\Exception\CurrencyMismatchException;
use App\Domain\Money\Exception\InvalidMoneyAmountException;
use App\Domain\Shared\Exception\InvalidIdentifierException;
use App\Http\Exception\InvalidRequestException;
use App\Infrastructure\Webhook\Exception\InvalidWebhookSignatureException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Translates domain, transport, and validation exceptions into the JSON
 * error envelope the API contract documents. Anything not listed here
 * falls through to Symfony's default exception handling (500 in prod).
 *
 * The mapping intentionally lives in one place so HTTP status codes
 * stay consistent across endpoints.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $response = $this->responseFor($exception);
        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    private function responseFor(\Throwable $exception): ?JsonResponse
    {
        return match (true) {
            $exception instanceof InvalidWebhookSignatureException => $this->envelope(
                Response::HTTP_UNAUTHORIZED,
                'INVALID_SIGNATURE',
                $exception->getMessage(),
            ),
            $exception instanceof CardNotFoundException => $this->envelope(
                Response::HTTP_NOT_FOUND,
                'CARD_NOT_FOUND',
                $exception->getMessage(),
            ),
            $exception instanceof InvalidCardStateTransitionException,
            $exception instanceof InvalidAuthorizationStateException => $this->envelope(
                Response::HTTP_CONFLICT,
                'INVALID_STATE_TRANSITION',
                $exception->getMessage(),
            ),
            $exception instanceof InvalidIdentifierException,
            $exception instanceof InvalidMerchantCategoryCodeException,
            $exception instanceof InvalidMoneyAmountException,
            $exception instanceof InvalidSpendingLimitsException,
            $exception instanceof CurrencyMismatchException,
            $exception instanceof InvalidRequestException => $this->envelope(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION_ERROR',
                $exception->getMessage(),
            ),
            default => null,
        };
    }

    private function envelope(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
