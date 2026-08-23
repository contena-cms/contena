<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\Config;

/**
 * @internal
 */
class TransportConfigProvider
{
    public function __construct(
        private readonly MetricConfigProvider $metricConfigProvider,
        private readonly ?string $namespace = null,
    ) {
    }

    public function getTransportConfig(): TransportConfig
    {
        return new TransportConfig(
            metricsConfig: $this->metricConfigProvider->all(),
            namespace: $this->namespace,
        );
    }
}
