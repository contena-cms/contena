<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserAuthThrottledException extends UserException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::CUSTOMER_AUTH_THROTTLED,
            'User auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->waitTime],
            $e
        );
    }

    public function getWaitTime(): int
    {
        return $this->waitTime;
    }
}
