<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\ScheduledTask;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('fundamentals@after-sales')]
class CleanupImportExportFileTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'import_export_file.cleanup';
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
