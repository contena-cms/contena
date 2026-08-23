<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context\Cleanup;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupChannelContextTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'channel_context.cleanup';
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
