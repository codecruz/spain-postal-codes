<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cross-cutting concerns of the public, read-only JSON API:
 * JSON error bodies, CORS and HTTP caching.
 */
final class ApiSubscriber implements EventSubscriberInterface
{
    /** The dataset only changes on deploy, so responses can be cached by clients and proxies. */
    private const CACHE_TTL = 3600;

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the framework logger (priority 0), before the HTML error renderer (-128).
            KernelEvents::EXCEPTION => ['onException', -64],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $response = ApiResponse::error($exception->getStatusCode(), $exception->getMessage());
            $response->headers->add($exception->getHeaders());
        } else {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $response = ApiResponse::error($status, $this->debug ? $exception->getMessage() : 'Internal server error.');
        }

        $event->setResponse($response);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');

        if ($request->isMethodCacheable() && $response->isSuccessful()) {
            $response->setPublic();
            $response->setMaxAge(self::CACHE_TTL);
        }
    }
}
