<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 */
class SystemHeartbeatTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app.system_heartbeat';
    }

    public static function getDefaultInterval(): int
    {
        return self::WEEKLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
