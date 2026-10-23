<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
class CleanupWebhookEventLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'webhook_event_log.cleanup';
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
