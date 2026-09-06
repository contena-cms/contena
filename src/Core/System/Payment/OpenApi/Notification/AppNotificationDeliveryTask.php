<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Notification;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
class AppNotificationDeliveryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'payment.app_notification.deliver';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
