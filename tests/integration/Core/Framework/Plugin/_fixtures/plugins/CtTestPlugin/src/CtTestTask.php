<?php declare(strict_types=1);

namespace CtTestPlugin;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CtTestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ct_test.test_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }
}
