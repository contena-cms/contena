<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Contena\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Contena\Core\Framework\Telemetry\Metrics\Metric\Type;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MetricNotSupportedException::class)]
class MetricNotSupportedExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $transport = static::createStub(MetricTransportInterface::class);
        $metric = Metric::fromArray(['name' => 'test', 'type' => Type::COUNTER, 'value' => 1]);
        $exception = new MetricNotSupportedException($metric, $transport);
        static::assertSame('TELEMETRY__METRIC_NOT_SUPPORTED', $exception->getErrorCode());
    }
}
