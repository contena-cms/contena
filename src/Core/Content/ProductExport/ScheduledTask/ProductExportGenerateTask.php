<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\ScheduledTask;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('inventory')]
class ProductExportGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_export_generate_task';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
