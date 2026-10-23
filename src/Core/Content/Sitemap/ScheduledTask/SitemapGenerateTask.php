<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SitemapGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'contena.sitemap_generate';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('contena.sitemap.scheduled_task.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
