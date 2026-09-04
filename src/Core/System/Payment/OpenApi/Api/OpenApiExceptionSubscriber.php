<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;

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
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/payment/')) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
            $code = $exception->getErrorCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof SymfonyHttpException) {
            $status = $exception->getStatusCode();
            $code = PaymentException::INVALID_REQUEST;
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'The payment request is invalid.';

            $validation = $exception;
            while ($validation instanceof SymfonyHttpException) {
                $validation = $validation->getPrevious();
            }
            if ($validation instanceof ValidationFailedException) {
                $requestData = $event->getRequest()->request->all();
                foreach ($validation->getViolations() as $violation) {
                    $propertyPath = $violation->getPropertyPath();
                    $parameter = self::snakeCase($propertyPath);
                    if ($propertyPath !== '' && ($violation->getConstraint() instanceof NotBlank || !\array_key_exists($parameter, $requestData))) {
                        $error = OpenApiException::missingParameter($parameter);
                        $status = $error->getStatusCode();
                        $code = $error->getErrorCode();
                        $message = $error->getMessage();
                        break;
                    }
                }
            }
        } else {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $code = self::INTERNAL_ERROR;
            $message = 'The payment request could not be processed.';
        }

        $event->setResponse(new JsonResponse(OpenApiResponse::error($code, $message), $status));
    }

    private static function snakeCase(string $propertyPath): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $propertyPath));
    }
}
