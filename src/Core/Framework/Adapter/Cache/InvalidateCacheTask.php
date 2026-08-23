<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Contena\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class InvalidateCacheTask extends ScheduledTask implements DeduplicatableMessageInterface
{
    public static function getTaskName(): string
    {
        return 'contena.invalidate_cache';
    }

    public static function getDefaultInterval(): int
    {
        // Run every five minutes
        return self::MINUTELY * 5;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }

    public function deduplicationId(): ?string
    {
        return 'invalidate-cache-task';
    }
}
