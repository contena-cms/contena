<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\Config;

use Contena\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 *
 * @phpstan-import-type MetricDefinition from MetricConfig
 */
class MetricConfigProvider
{
    /**
     * @var array<string, MetricConfig>
     */
    private readonly array $metricsByName;

    /**
     * @param array<string, MetricDefinition> $definitions
     */
    public function __construct(array $definitions)
    {
        $metricsByName = [];
        foreach ($definitions as $name => $definition) {
            $metricsByName[$name] = MetricConfig::fromDefinition($name, $definition);
        }

        $this->metricsByName = $metricsByName;
    }

    public function get(string $name): MetricConfig
    {
        return $this->metricsByName[$name] ?? throw TelemetryException::metricMissingConfiguration($name);
    }

    /**
     * @return array<MetricConfig>
     */
    public function all(): array
    {
        return array_values($this->metricsByName);
    }
}
