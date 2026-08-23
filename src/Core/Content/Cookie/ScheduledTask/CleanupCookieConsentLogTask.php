<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupCookieConsentLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'cookie_consent_log.cleanup';
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
