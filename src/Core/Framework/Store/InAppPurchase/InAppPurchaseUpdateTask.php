<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\InAppPurchase;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class InAppPurchaseUpdateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'in-app-purchase.update';
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
