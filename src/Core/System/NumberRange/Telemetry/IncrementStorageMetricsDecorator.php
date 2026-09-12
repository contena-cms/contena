<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Telemetry;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;

/**
 * Emits `number_range.allocation.duration` around {@see AbstractIncrementStorage::reserve()} - the
 * row-level-locked hot path on the MySQL backend. Rising p95/p99 with `storage=mysql` under load
 * signals `number_range_state` lock contention, the leading indicator for switching to the Redis storage.
 *
 * Decorates the configured storage so one instrumentation point covers the MySQL, Redis and any custom backend.
 * `preview`/`list`/`set` are administrative paths and pass through unmeasured.
 *
 * The `storage` label is instance-constant (`contena.number_range.increment_storage`), resolved once via DI.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled, no compiler-pass gating.
 *
 * @internal
 *
 * @final
 */
class IncrementStorageMetricsDecorator extends AbstractIncrementStorage
{
    private const string RESULT_SUCCESS = 'success';
    private const string RESULT_FAILED = 'failed';

    public function __construct(
        private readonly AbstractIncrementStorage $decorated,
        private readonly Meter $meter,
        private readonly NumberRangeTypeResolver $typeResolver,
        private readonly string $storage,
    ) {
    }

    public function reserve(array $config, Context $context): int
    {
        $result = self::RESULT_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            return $this->decorated->reserve($config, $context);
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'number_range.allocation.duration',
                value: $timer->getElapsedMs(),
                labels: [
                    'number_range_type' => $this->typeResolver->resolve($config['technical_name'] ?? null),
                    'storage' => $this->storage,
                    'result' => $result,
                ],
            ));
        }
    }

    public function preview(array $config, Context $context): int
    {
        return $this->decorated->preview($config, $context);
    }

    public function list(Context $context): array
    {
        return $this->decorated->list($context);
    }

    public function set(IncrementState $state, Context $context): void
    {
        $this->decorated->set($state, $context);
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        return $this->decorated;
    }
}
