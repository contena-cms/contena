<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
class CleanupCorruptedMediaTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'media.cleanup_corrupted_media';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
