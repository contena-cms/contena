<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class UpdateBlogStreamMappingTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'blog_stream.mapping.update';
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
