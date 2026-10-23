<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupUserRecoveryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'user.cleanup_customer_recovery';
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
