<?php declare(strict_types=1);

namespace Contena\Core\Framework\Notification;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class NotificationException extends HttpException
{
    public const string INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__NOTIFICATION_INVALID_REQUEST_PARAMETER';
    public const string API_NOTIFICATION_THROTTLED = 'FRAMEWORK__NOTIFICATION_THROTTLED';

    /**
     * @param class-string<ContextSource> $actual
     */
    public static function invalidAdminSource(string $actual): InvalidContextSourceException
    {
        return new InvalidContextSourceException(AdminApiSource::class, $actual);
    }

    public static function invalidRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function notificationThrottled(int $waitTime, \Throwable $e): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::API_NOTIFICATION_THROTTLED,
            'Notification throttled for {{ seconds }} seconds.',
            ['seconds' => $waitTime],
            $e
        );
    }
}
