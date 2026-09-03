<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\HttpException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\OpenApi\OpenApiRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
final class OpenApiExceptionSubscriber implements EventSubscriberInterface
{
    final public const string INTERNAL_ERROR = 'PAYMENT_API__INTERNAL_ERROR';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);
        if (!\is_array($scopes) || !\in_array(OpenApiRouteScope::ID, $scopes, true)) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
            $code = $exception->getErrorCode();
            $message = $exception->getMessage();
        } else {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $code = self::INTERNAL_ERROR;
            $message = 'The payment request could not be processed.';
        }

        $event->setResponse(new JsonResponse(OpenApiResponse::error($code, $message), $status));
    }
}
