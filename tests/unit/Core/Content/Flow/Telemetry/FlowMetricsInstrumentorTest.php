<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[CoversClass(FlowMetricsInstrumentor::class)]
class FlowMetricsInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testSuccessfulExecutionEmitsDurationWithResolvedGroup(): void
    {
        $this->createInstrumentor()->measureExecution($this->createFlow('user.recovery.request'), fn () => null);

        $duration = $this->getMetric('flow.execution.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0, $duration->value);
        static::assertSame(['trigger_group' => 'trigger_group_label:user.recovery.request', 'result' => 'success'], $duration->labels);
    }

    public function testCallbackIsInvokedExactlyOnce(): void
    {
        $calls = 0;

        $this->createInstrumentor()->measureExecution($this->createFlow('user.recovery.request'), function () use (&$calls): void {
            ++$calls;
        });

        static::assertSame(1, $calls);
    }

    public function testFailingCallbackIsRethrownAndDurationRecordedAsFailed(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measureExecution($this->createFlow('user.recovery.request'), function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('boom', $thrown->getMessage());

        $duration = $this->getMetric('flow.execution.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('failed', $duration->labels['result']);
        static::assertSame('trigger_group_label:user.recovery.request', $duration->labels['trigger_group']);
    }

    private function getMetric(string $name): ?ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }

    private function createInstrumentor(): FlowMetricsInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        // Pass-through resolver stub: echoes the event name back with a fixed prefix, so it's easy to validate
        $triggerGroupResolver = static::createStub(TriggerGroupResolver::class);
        $triggerGroupResolver->method('resolve')->willReturnCallback(
            static fn (string $eventName): string => 'trigger_group_label:' . $eventName
        );

        return new FlowMetricsInstrumentor($meter, $triggerGroupResolver);
    }

    private function createFlow(string $eventName): StorableFlow
    {
        return new StorableFlow($eventName, Context::createDefaultContext());
    }
}
