<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cleanup;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupUnusedDownloadMediaTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'blog_download.media.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 2628000; // 1 month
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
