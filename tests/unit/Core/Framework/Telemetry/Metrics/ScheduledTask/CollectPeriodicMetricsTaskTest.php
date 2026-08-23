<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Metrics\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Telemetry\Metrics\ScheduledTask\CollectPeriodicMetricsTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(CollectPeriodicMetricsTask::class)]
class CollectPeriodicMetricsTaskTest extends TestCase
{
    public function testTaskName(): void
    {
        static::assertSame('telemetry.collect_periodic_metrics', CollectPeriodicMetricsTask::getTaskName());
    }

    public function testDefaultInterval(): void
    {
        static::assertSame(300, CollectPeriodicMetricsTask::getDefaultInterval());
    }

    public function testShouldRunWhenEnabled(): void
    {
        $bag = new ParameterBag(['contena.telemetry.metrics.enabled' => true]);

        static::assertTrue(CollectPeriodicMetricsTask::shouldRun($bag));
    }

    public function testShouldNotRunWhenDisabled(): void
    {
        $bag = new ParameterBag(['contena.telemetry.metrics.enabled' => false]);

        static::assertFalse(CollectPeriodicMetricsTask::shouldRun($bag));
    }

    public function testShouldRescheduleOnFailure(): void
    {
        static::assertTrue(CollectPeriodicMetricsTask::shouldRescheduleOnFailure());
    }
}
