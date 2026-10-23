<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cleanup;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupBlogKeywordDictionaryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'blog_keyword_dictionary.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::WEEKLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
