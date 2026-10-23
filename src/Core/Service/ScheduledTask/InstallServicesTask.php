<?php declare(strict_types=1);

namespace Contena\Core\Service\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
class InstallServicesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'services.install';
    }

    public static function getDefaultInterval(): int
    {
        return parent::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
