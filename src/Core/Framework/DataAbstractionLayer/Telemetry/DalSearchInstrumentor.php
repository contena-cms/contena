<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Telemetry;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Contena\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Contena\Core\Framework\Telemetry\TelemetryException;

/**
 * Emits the `dal.search.duration` metric for DAL read/search/aggregate operations.
 *
 * The instrumentor does not include profiling, as in this case metrics are emitted only for top-level operations,
 * while profiler should create spans for recursive calls.
 *
 * The DAL read path is extremely hot, so the instrumentor is guarded by a single flag resolved once in the constructor:
 * `global telemetry enabled` && `instrumentor metrics are enabled`. When it is off, `measure()` just runs the callback.
 *
 * @internal
 *
 * @final
 */
class DalSearchInstrumentor
{
    public const OPERATION_SEARCH = 'search';
    public const OPERATION_SEARCH_IDS = 'searchIds';
    public const OPERATION_AGGREGATE = 'aggregate';

    private const BACKEND_SQL = 'sql';

    private const BACKEND_ELASTICSEARCH = 'elasticsearch';

    private const DURATION_METRIC = 'dal.search.duration';

    private const ELASTICSEARCH_RESULT_STATE = 'loaded-by-elastic';

    private readonly bool $enabled;

    public function __construct(
        private readonly Meter $meter,
        private readonly EntityGroupResolver $entityGroupResolver,
        MetricConfigProvider $metricConfigProvider,
        bool $enabled,
    ) {
        $this->enabled = $enabled && $this->isMetricEnabled($metricConfigProvider);
    }

    /**
     * @template TReturn
     *
     * @param self::OPERATION_* $operation
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function measure(string $operation, EntityDefinition $definition, Criteria $criteria, \Closure $callback): mixed
    {
        if (!$this->enabled) {
            return $callback();
        }

        $timer = ElapsedTimer::start();
        try {
            $result = $callback();
        } finally {
            $durationMs = $timer->getElapsedMs();
        }

        $this->meter->emit(new ConfiguredMetric(
            name: self::DURATION_METRIC,
            value: $durationMs,
            labels: [
                'entity_group' => $this->entityGroupResolver->resolve($definition->getEntityName()),
                'operation' => $operation,
                'es_aware' => $criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE) ? 'yes' : 'no',
                'backend' => $this->resolveBackend($result),
            ],
        ));

        return $result;
    }

    private function isMetricEnabled(MetricConfigProvider $metricConfigProvider): bool
    {
        try {
            return $metricConfigProvider->get(self::DURATION_METRIC)->enabled;
        } catch (TelemetryException) {
            return false;
        }
    }

    private function resolveBackend(mixed $result): string
    {
        if (($result instanceof EntitySearchResult
            || $result instanceof IdSearchResult
            || $result instanceof AggregationResultCollection)
            && $result->hasState(self::ELASTICSEARCH_RESULT_STATE)
        ) {
            return self::BACKEND_ELASTICSEARCH;
        }

        return self::BACKEND_SQL;
    }
}
