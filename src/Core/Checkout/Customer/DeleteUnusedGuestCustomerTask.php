<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class DeleteUnusedGuestCustomerTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'customer.delete_unused_guests';
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
