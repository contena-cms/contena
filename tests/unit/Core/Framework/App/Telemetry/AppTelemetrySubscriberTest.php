<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Telemetry;

use Contena\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppTelemetrySubscriber::class)]
class AppTelemetrySubscriberTest extends TestCase
{
    public function testEmitAppInstalledMetric(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'app.install.count' && $metric->value === 1;
            }));

        $subscriber = new AppTelemetrySubscriber($meter);
        $subscriber->emitAppInstalledMetric();
    }
}
