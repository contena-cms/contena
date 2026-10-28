<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductStream\ScheduledTask;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('inventory')]
class UpdateProductStreamMappingTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_stream.mapping.update';
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
