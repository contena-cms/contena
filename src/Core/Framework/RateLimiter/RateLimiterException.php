<?php declare(strict_types=1);

namespace Contena\Core\Framework\RateLimiter;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class RateLimiterException extends HttpException
{
    public const string RATE_LIMIT_EXCEEDED = 'FRAMEWORK__RATE_LIMIT_EXCEEDED';
    public const string FACTORY_NOT_FOUND = 'FRAMEWORK__RATE_LIMITER_FACTORY_NOT_FOUND';
    public const string BACKOFF_UNSERIALIZATION_FAILED = 'FRAMEWORK__RATE_LIMITER_BACKOFF_UNSERIALIZATION_FAILED';

    public static function limitExceeded(int $retryAfter, ?\Throwable $e = null): self
    {
        return new RateLimitExceededException($retryAfter, $e);
    }

    public static function backoffUnserializationFailed(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BACKOFF_UNSERIALIZATION_FAILED,
            'Cannot unserialize {{ class }}.',
            ['class' => $class]
        );
    }

    public static function factoryNotFound(string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FACTORY_NOT_FOUND,
            'Rate limiter factory for route "{{ route }}" not found.',
            ['route' => $route]
        );
    }
}
