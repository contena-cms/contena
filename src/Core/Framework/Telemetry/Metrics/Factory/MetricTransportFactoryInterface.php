<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\Factory;

use Contena\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

interface MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface;
}
