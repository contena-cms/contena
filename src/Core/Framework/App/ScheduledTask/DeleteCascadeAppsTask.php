<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DeleteCascadeAppsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app_delete';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
