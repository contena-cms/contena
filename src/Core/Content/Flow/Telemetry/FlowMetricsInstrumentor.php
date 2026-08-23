<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Telemetry;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Telemetry collaborator for {@see \Contena\Core\Content\Flow\Dispatching\FlowExecutor}: emits
 * `flow.execution.duration` once per executed flow (a single inbound event fans out across all matched flows,
 * each timed separately). The histogram also carries its own occurrence count - can be used to calculate
 * throughput/error rate (use result label).
 *
 * This is the flow engine's end-to-end wall time (orchestration plus the in-process actions). Heavy actions
 * that offload to queue, like mail and webhooks - are timed by the message queue metrics.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled, no compiler-pass gating.
 *
 * @internal
 *
 * @final
 */
class FlowMetricsInstrumentor
{
    private const string RESULT_SUCCESS = 'success';
    private const string RESULT_FAILED = 'failed';

    public function __construct(
        private readonly Meter $meter,
        private readonly TriggerGroupResolver $triggerGroupResolver,
    ) {
    }

    /**
     * @param \Closure(): void $callback
     */
    public function measureExecution(StorableFlow $event, \Closure $callback): void
    {
        $result = self::RESULT_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            $callback();
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'flow.execution.duration',
                value: $timer->getElapsedMs(),
                labels: [
                    'trigger_group' => $this->triggerGroupResolver->resolve($event->getName()),
                    'result' => $result,
                ],
            ));
        }
    }
}
