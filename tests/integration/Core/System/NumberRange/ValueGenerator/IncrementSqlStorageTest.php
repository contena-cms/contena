<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class IncrementSqlStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementSqlStorage $storage;

    private Connection $connection;

    private Context $platformContext;

    protected function setUp(): void
    {
        $this->storage = static::getContainer()->get(IncrementSqlStorage::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->platformContext = Context::createDefaultContext();
        $this->connection->executeStatement('DELETE FROM `number_range_state`');
    }

    public function testReserveReturnsIncrementIfStartOfPatternIsLowerThenTheIncrement(): void
    {
        $config = $this->createConfig(5);
        $this->storage->set($this->state($config, 10), $this->platformContext);

        static::assertSame(11, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(12, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsWithoutStart(): void
    {
        $config = $this->createConfig(null);
        $this->storage->set($this->state($config, 10), $this->platformContext);

        static::assertSame(11, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(12, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsWithZeroStartValues(): void
    {
        $config = $this->createConfig(0);
        $this->storage->set($this->state($config, 0), $this->platformContext);

        static::assertSame(1, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(2, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsWithZeroStartValuesAndNoValueStored(): void
    {
        $config = $this->createConfig(0);

        static::assertSame(0, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(1, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsWithoutStartAndUnset(): void
    {
        $config = $this->createConfig(null);

        static::assertSame(1, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(2, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsStartValueIfItIsHigherThanCurrentIncrement(): void
    {
        $config = $this->createConfig(10);
        $this->storage->set($this->state($config, 5), $this->platformContext);

        static::assertSame(10, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(11, $this->storage->reserve($config, $this->platformContext));
    }

    public function testReserveReturnsStartValueIfNoValueIsSet(): void
    {
        $config = $this->createConfig(10);

        static::assertSame(10, $this->storage->reserve($config, $this->platformContext));
        static::assertSame(11, $this->storage->reserve($config, $this->platformContext));
    }

    public function testPreviewIfValueIsNotSetAndNoStart(): void
    {
        $config = $this->createConfig(null);

        static::assertSame(1, $this->storage->preview($config, $this->platformContext));
        static::assertSame(1, $this->storage->preview($config, $this->platformContext));
    }

    public function testPreviewWillReturnStartValueIfNoValueIsSet(): void
    {
        $config = $this->createConfig(10);

        static::assertSame(10, $this->storage->preview($config, $this->platformContext));
        static::assertSame(10, $this->storage->preview($config, $this->platformContext));
    }

    public function testPreviewReturnsWithZeroStartValues(): void
    {
        $config = $this->createConfig(0);
        $this->storage->set($this->state($config, 0), $this->platformContext);

        static::assertSame(1, $this->storage->preview($config, $this->platformContext));
        static::assertSame(1, $this->storage->preview($config, $this->platformContext));
    }

    public function testPreviewReturnsWithZeroStartValuesAndNoValueStored(): void
    {
        $config = $this->createConfig(0);

        static::assertSame(0, $this->storage->preview($config, $this->platformContext));
        static::assertSame(0, $this->storage->preview($config, $this->platformContext));
    }

    public function testPreviewWillReturnStartValueIfItHigherThanCurrentIncrementValue(): void
    {
        $config = $this->createConfig(10);
        $this->storage->set($this->state($config, 5), $this->platformContext);

        static::assertSame(10, $this->storage->preview($config, $this->platformContext));
        static::assertSame(10, $this->storage->preview($config, $this->platformContext));
    }

    public function testPreviewWillReturnNextValueIfIncrementIsHigherThanStartValue(): void
    {
        $config = $this->createConfig(10);
        $this->storage->set($this->state($config, 15), $this->platformContext);

        static::assertSame(16, $this->storage->preview($config, $this->platformContext));
        static::assertSame(16, $this->storage->preview($config, $this->platformContext));
    }

    public function testSetAndList(): void
    {
        $first = $this->createConfig(1);
        $second = $this->createConfig(1);
        $states = [
            $first['id'] => $this->state($first, 10),
            $second['id'] => $this->state($second, 5),
        ];

        static::assertSame([], $this->storage->list($this->platformContext));
        foreach ($states as $state) {
            $this->storage->set($state, $this->platformContext);
        }

        static::assertEquals($states, $this->storage->list($this->platformContext));
    }

    public function testListUsesOnlyTheExactContextScope(): void
    {
        $tenantAContext = Context::createTenantContext($this->createTenant('Number range SQL tenant A')->id);
        $tenantBContext = Context::createTenantContext($this->createTenant('Number range SQL tenant B')->id);

        $platformConfig = $this->createConfig(1, null, $this->platformContext);
        $tenantAConfig = $this->createConfig(1, null, $tenantAContext);
        $tenantBConfig = $this->createConfig(1, null, $tenantBContext);
        $this->storage->set($this->state($platformConfig, 10), $this->platformContext);
        $this->storage->set($this->state($tenantAConfig, 20), $tenantAContext);
        $this->storage->set($this->state($tenantBConfig, 30), $tenantBContext);

        static::assertSame([$platformConfig['id'] => $this->state($platformConfig, 10)], $this->storage->list($this->platformContext));
        static::assertSame([$tenantAConfig['id'] => $this->state($tenantAConfig, 20)], $this->storage->list($tenantAContext));
        static::assertSame([$tenantBConfig['id'] => $this->state($tenantBConfig, 30)], $this->storage->list($tenantBContext));
        static::assertSame([$platformConfig['id'] => $this->state($platformConfig, 10)], $this->storage->list(Context::createGlobalContext()));
    }

    public function testStateFromAnotherScopeIsRejectedBeforeWriting(): void
    {
        $tenantContext = Context::createTenantContext($this->createTenant('Number range SQL mismatch')->id);
        $config = $this->createConfig(1, null, $tenantContext);

        $this->expectException(\InvalidArgumentException::class);
        $this->storage->set($this->state($config, 10), $this->platformContext);
    }

    public function testRangeFromAnotherScopeCannotCreateState(): void
    {
        $platformConfig = $this->createConfig(1);
        $tenantContext = Context::createTenantContext($this->createTenant('Number range SQL foreign key')->id);
        $tenantConfig = [...$platformConfig, 'dataScopeId' => $tenantContext->getDataScopeId()];

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->storage->reserve($tenantConfig, $tenantContext);
    }

    /**
     * @return array{id: string, dataScopeId: string, pattern: string, start: ?int}
     */
    private function createConfig(?int $start, ?string $id = null, ?Context $context = null): array
    {
        $context ??= $this->platformContext;
        $id ??= Uuid::randomHex();
        $typeId = $this->connection->fetchOne('SELECT `id` FROM `number_range_type` LIMIT 1');
        static::assertIsString($typeId);
        $this->connection->insert('number_range', [
            'id' => Uuid::fromHexToBytes($id),
            'data_scope_id' => Uuid::fromHexToBytes($context->getDataScopeId()),
            'type_id' => $typeId,
            'global' => 1,
            'pattern' => '{n}',
            'start' => $start ?? 1,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        return ['id' => $id, 'dataScopeId' => $context->getDataScopeId(), 'pattern' => 'n', 'start' => $start];
    }

    /**
     * @param array{id: string, dataScopeId: string, pattern: string, start: ?int} $config
     */
    private function state(array $config, int $value): IncrementState
    {
        return new IncrementState($config['dataScopeId'], $config['id'], $value);
    }
}
