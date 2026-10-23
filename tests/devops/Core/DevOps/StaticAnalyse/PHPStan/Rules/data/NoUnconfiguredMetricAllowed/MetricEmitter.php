<?php declare(strict_types=1);

namespace Contena\Fixture\DevOps\PHPStan\NoUnconfiguredMetricAllowed;

use Contena\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

class MetricEmitter
{
    /**
     * @return array<object>
     */
    public function create(): array
    {
        return [
            new ConfiguredMetric('configured.metric', 1),
            new ConfiguredMetric('unconfigured.metric', 1),
            new ConfiguredMetric(name: 'unconfigured.named.metric', value: 1),
            new DurationMetric('configured.duration'),
            new DurationMetric('unconfigured.duration'),
            new DurationMetric(name: 'unconfigured.named.duration'),
            new DurationMetric($this->dynamicName()),
        ];
    }

    private function dynamicName(): string
    {
        return 'runtime.metric';
    }
}
