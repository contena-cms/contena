<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\NumberRangeCollection;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[CoversClass(IncrementRedisStorage::class)]
#[RequiresPhpExtension('redis')]
class IncrementRedisStorageTest extends TestCase
{
    private Context $context;

    private MockObject&LockFactory $lockFactoryMock;

    private MockObject&\Redis $redisMock;

    private IncrementRedisStorage $storage;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->lockFactoryMock = $this->createMock(LockFactory::class);
        $this->redisMock = $this->createMock('Redis');

        /** @var StaticEntityRepository<NumberRangeCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $this->storage = new IncrementRedisStorage(
            $this->redisMock,
            $this->lockFactoryMock,
            $repository,
        );
    }

    public function testReserveReturnsIncrementWhenItReachedTheConfiguredStart(): void
    {
        $config = $this->config(5);

        $this->lockFactoryMock->expects($this->never())->method('createLock');
        $this->redisMock->expects($this->once())
            ->method('incr')
            ->with($this->getKey($this->context, $config['id']))
            ->willReturn(10);

        static::assertSame(10, $this->storage->reserve($config, $this->context));
    }

    public function testReserveWithoutStartUsesTheIncrement(): void
    {
        $config = $this->config(null);

        $this->lockFactoryMock->expects($this->never())->method('createLock');
        $this->redisMock->expects($this->once())
            ->method('incr')
            ->with($this->getKey($this->context, $config['id']))
            ->willReturn(10);

        static::assertSame(10, $this->storage->reserve($config, $this->context));
    }

    public function testReserveDoesNotLockWhenIncrementEqualsStart(): void
    {
        $config = $this->config(5);

        $this->lockFactoryMock->expects($this->never())->method('createLock');
        $this->redisMock->expects($this->once())->method('incr')->willReturn(5);

        static::assertSame(5, $this->storage->reserve($config, $this->context));
    }

    public function testReserveRaisesIncrementToStartWhileHoldingScopeSpecificLock(): void
    {
        $config = $this->config(10);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactoryMock->expects($this->once())
            ->method('createLock')
            ->with('number-range-' . $this->context->getDataScopeId() . '-' . $config['id'])
            ->willReturn($lock);
        $this->redisMock->expects($this->once())
            ->method('incr')
            ->with($this->getKey($this->context, $config['id']))
            ->willReturn(5);
        $this->redisMock->expects($this->once())
            ->method('incrBy')
            ->with($this->getKey($this->context, $config['id']), 5)
            ->willReturn(10);

        static::assertSame(10, $this->storage->reserve($config, $this->context));
    }

    public function testReserveReturnsCurrentIncrementWhenLockIsUnavailable(): void
    {
        $config = $this->config(10);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $this->lockFactoryMock->expects($this->once())->method('createLock')->willReturn($lock);
        $this->redisMock->expects($this->once())->method('incr')->willReturn(5);
        $this->redisMock->expects($this->never())->method('incrBy');

        static::assertSame(5, $this->storage->reserve($config, $this->context));
    }

    public function testPreviewUsesStartWhenStateIsMissingOrLower(): void
    {
        $config = $this->config(10);

        $this->redisMock->expects($this->exactly(2))
            ->method('get')
            ->with($this->getKey($this->context, $config['id']))
            ->willReturnOnConsecutiveCalls(null, 8);

        static::assertSame(10, $this->storage->preview($config, $this->context));
        static::assertSame(10, $this->storage->preview($config, $this->context));
    }

    public function testPreviewReturnsNextStoredValue(): void
    {
        $config = $this->config(10);
        $this->redisMock->expects($this->once())->method('get')->willReturn(15);

        static::assertSame(16, $this->storage->preview($config, $this->context));
    }

    public function testListReturnsTypedStatesForTheExactContext(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $idSearchResult = new IdSearchResult(
            2,
            [
                $firstId => ['data' => [], 'primaryKey' => $firstId],
                $secondId => ['data' => [], 'primaryKey' => $secondId],
            ],
            new Criteria(),
            $this->context,
        );

        $this->redisMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls('10', '5');

        /** @var StaticEntityRepository<NumberRangeCollection> $repository */
        $repository = new StaticEntityRepository([$idSearchResult]);
        $storage = new IncrementRedisStorage($this->redisMock, $this->lockFactoryMock, $repository);

        static::assertEquals([
            $firstId => new IncrementState($this->context->getDataScopeId(), $firstId, 10),
            $secondId => new IncrementState($this->context->getDataScopeId(), $secondId, 5),
        ], $storage->list($this->context));
    }

    public function testSetUsesScopeSpecificKey(): void
    {
        $state = new IncrementState($this->context->getDataScopeId(), Uuid::randomHex(), 10);

        $this->redisMock->expects($this->once())
            ->method('set')
            ->with($this->getKey($this->context, $state->numberRangeId), 10);

        $this->storage->set($state, $this->context);
    }

    public function testSameConfigurationIdUsesDifferentKeysAcrossScopes(): void
    {
        $numberRangeId = Uuid::randomHex();
        $tenantContext = Context::createTenantContext(Uuid::randomHex());
        $platformConfig = $this->config(1, $numberRangeId, $this->context);
        $tenantConfig = $this->config(1, $numberRangeId, $tenantContext);

        $this->redisMock->expects($this->exactly(2))
            ->method('incr')
            ->willReturn(1);

        $this->storage->reserve($platformConfig, $this->context);
        $this->storage->reserve($tenantConfig, $tenantContext);
    }

    public function testConfigurationFromAnotherScopeIsRejectedBeforeRedisAccess(): void
    {
        $config = $this->config(1);
        $tenantContext = Context::createTenantContext(Uuid::randomHex());
        $this->redisMock->expects($this->never())->method('get');

        $this->expectException(\InvalidArgumentException::class);
        $this->storage->preview($config, $tenantContext);
    }

    /**
     * @return array{id: string, dataScopeId: string, pattern: string, start: ?int}
     */
    private function config(?int $start, ?string $id = null, ?Context $context = null): array
    {
        $context ??= $this->context;

        return [
            'id' => $id ?? Uuid::randomHex(),
            'dataScopeId' => $context->getDataScopeId(),
            'start' => $start,
            'pattern' => 'n',
        ];
    }

    private function getKey(Context $context, string $id): string
    {
        return \sprintf('number_range:%s:%s', $context->getDataScopeId(), $id);
    }
}
