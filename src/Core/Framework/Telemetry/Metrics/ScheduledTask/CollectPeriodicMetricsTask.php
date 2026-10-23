<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 */
class CollectPeriodicMetricsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'telemetry.collect_periodic_metrics';
    }

    public static function getDefaultInterval(): int
    {
        return 5 * self::MINUTELY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('contena.telemetry.metrics.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
