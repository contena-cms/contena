<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DeleteUnusedGuestUserTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'user.delete_unused_guests';
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
