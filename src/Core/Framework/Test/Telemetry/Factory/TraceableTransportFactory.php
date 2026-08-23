<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Telemetry\Factory;

use Contena\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Contena\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Contena\Core\Framework\Test\Telemetry\Transport\TraceableTransport;

/**
 * @internal
 */
class TraceableTransportFactory implements MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        return new TraceableTransport();
    }
}
