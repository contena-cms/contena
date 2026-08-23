<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberAuthThrottledException extends MemberException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $exception = null,
    ) {
        parent::__construct(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::MEMBER_AUTH_THROTTLED,
            'Member auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->waitTime],
            $exception,
        );
    }

    public function getWaitTime(): int
    {
        return $this->waitTime;
    }
}
