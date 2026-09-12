<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\NumberRange\Telemetry;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\Telemetry\IncrementStorageMetricsDecorator;
use Contena\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IncrementStorageMetricsDecorator::class)]
class IncrementStorageMetricsDecoratorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testReserveReturnsDecoratedValueAndEmitsDurationWithResolvedLabels(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $decorated->method('reserve')->willReturn(42);

        $context = Context::createDefaultContext();
        $result = $this->createDecorator($decorated, 'mysql')->reserve($this->config('member', $context), $context);

        static::assertSame(42, $result);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(
            [
                'number_range_type' => 'number_range_type_label:member',
                'storage' => 'mysql',
                'result' => 'success',
            ],
            $duration->labels,
        );
    }

    public function testMissingTechnicalNameForwardsNullToResolver(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $decorated->method('reserve')->willReturn(1);

        // creating config with missing technical_name
        $context = Context::createDefaultContext();
        $config = $this->config(null, $context);

        $this->createDecorator($decorated, 'mysql')->reserve($config, $context);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('number_range_type_label:', $duration->labels['number_range_type']);
    }

    public function testFailingReserveIsRethrownAndDurationRecordedAsFailed(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $exception = new \RuntimeException('lock wait timeout');
        $decorated->method('reserve')->willThrowException($exception);

        $thrown = null;
        $context = Context::createDefaultContext();

        try {
            $this->createDecorator($decorated, 'mysql')->reserve($this->config('member', $context), $context);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame($exception, $thrown);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('failed', $duration->labels['result']);
        static::assertSame('number_range_type_label:member', $duration->labels['number_range_type']);
        static::assertSame('mysql', $duration->labels['storage']);
    }

    public function testPreviewDelegatesAndEmitsNothing(): void
    {
        $context = Context::createDefaultContext();
        $config = $this->config('member', $context);

        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('preview')
            ->with($config, $context)
            ->willReturn(7);

        static::assertSame(7, $this->createDecorator($decorated, 'mysql')->preview($config, $context));
        static::assertSame([], $this->emitted);
    }

    public function testListDelegatesAndEmitsNothing(): void
    {
        $context = Context::createDefaultContext();
        $state = new IncrementState($context->getDataScopeId(), Uuid::randomHex(), 5);
        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('list')
            ->with($context)
            ->willReturn([$state->numberRangeId => $state]);

        static::assertSame([$state->numberRangeId => $state], $this->createDecorator($decorated, 'mysql')->list($context));
        static::assertSame([], $this->emitted);
    }

    public function testSetDelegatesAndEmitsNothing(): void
    {
        $context = Context::createDefaultContext();
        $state = new IncrementState($context->getDataScopeId(), Uuid::randomHex(), 99);
        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('set')
            ->with($state, $context);

        $this->createDecorator($decorated, 'mysql')->set($state, $context);

        static::assertSame([], $this->emitted);
    }

    public function testGetDecoratedReturnsDecoratedInstance(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);

        static::assertSame($decorated, $this->createDecorator($decorated, 'mysql')->getDecorated());
    }

    private function createDecorator(AbstractIncrementStorage $decorated, string $storage): IncrementStorageMetricsDecorator
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        // Pass-through resolver stub: echoes the technical name back with a fixed prefix, so it's easy to validate
        $typeResolver = static::createStub(NumberRangeTypeResolver::class);
        $typeResolver->method('resolve')->willReturnCallback(
            static fn (?string $technicalName): string => 'number_range_type_label:' . $technicalName
        );

        return new IncrementStorageMetricsDecorator($decorated, $meter, $typeResolver, $storage);
    }

    /**
     * @return array{id: string, dataScopeId: string, pattern: string, start: ?int, technical_name?: string}
     */
    private function config(?string $technicalName, Context $context): array
    {
        $config = [
            'id' => Uuid::randomHex(),
            'dataScopeId' => $context->getDataScopeId(),
            'pattern' => '{n}',
            'start' => 1,
        ];

        if ($technicalName !== null) {
            $config['technical_name'] = $technicalName;
        }

        return $config;
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
}
