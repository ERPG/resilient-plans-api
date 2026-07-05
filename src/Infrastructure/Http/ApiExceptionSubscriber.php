<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Renders any request throwable as the contract error envelope. On kernel.exception (not a controller
 * try/catch) so it also catches routing and argument-resolution failures, not just the action body.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof ApiException) {
            $event->setResponse($this->envelope(
                $throwable->httpStatusCode(),
                $throwable->errorCode(),
                $throwable->errorMessage(),
            ));

            return;
        }

        // Symfony's own HTTP errors (404 no route, 405 wrong method) keep their status, generic body.
        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $event->setResponse($this->envelope(
                $status,
                'http_error',
                Response::$statusTexts[$status] ?? 'Error',
            ));

            return;
        }

        $this->logger->error('Unhandled exception in HTTP request', ['exception' => $throwable]);
        $event->setResponse($this->envelope(500, 'internal_error', 'Internal Server Error'));
    }

    private function envelope(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['data' => null, 'error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }
}
