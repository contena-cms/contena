<?php declare(strict_types=1);

namespace Contena\Core\Framework\SystemCheck\Check;

/**
 * @codeCoverageIgnore
 */
enum SystemCheckExecutionContext: string
{
    case WEB = 'web';

    case CLI = 'cli';

    case PRE_ROLLOUT = 'pre_rollout';

    case RECURRENT = 'recurrent';

    /**
     * @return array<self>
     */
    public static function readiness(): array
    {
        return [self::PRE_ROLLOUT];
    }

    /**
     * @return array<self>
     */
    public static function longRunning(): array
    {
        return [self::CLI, self::RECURRENT, self::PRE_ROLLOUT];
    }
}
