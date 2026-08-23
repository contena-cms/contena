<?php

declare(strict_types=1);

namespace Contena\Frontend\Theme\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class DeleteThemeFilesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'theme.delete_files';
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
