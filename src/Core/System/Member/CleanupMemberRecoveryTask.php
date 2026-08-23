<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupMemberRecoveryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'member.cleanup_member_recovery';
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
