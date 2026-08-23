<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Contena\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Contena\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Contena\Core\Framework\Telemetry\Metrics\Metric\Type;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[CoversClass(MetricNotSupportedException::class)]
class MetricNotSupportedExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $transport = static::createStub(MetricTransportInterface::class);
        $metricConfig = new MetricConfig('test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);
        $metric = Metric::fromConfigured(new ConfiguredMetric('test', 1, []), $metricConfig, []);
        $exception = new MetricNotSupportedException($metric, $transport);
        static::assertSame('TELEMETRY__METRIC_NOT_SUPPORTED', $exception->getErrorCode());
    }
}
