<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Contena\Core\Test\Stub\System\NumberRange\ValueGenerator\IncrementArrayStorage;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
class IncrementStorageRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementStorageRegistry $registry;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `number_range_state`');
        $this->context = Context::createDefaultContext();
    }

    public function testGetDefaultStorage(): void
    {
        $this->registry = static::getContainer()->get(IncrementStorageRegistry::class);

        static::assertInstanceOf(IncrementSqlStorage::class, $this->registry->getStorage());
    }

    public function testMigrateToSqlStorage(): void
    {
        $first = $this->createConfigAndState(10);
        $second = $this->createConfigAndState(4);
        $arrayStorage = new IncrementArrayStorage([$first, $second]);
        $sqlStorage = static::getContainer()->get(IncrementSqlStorage::class);

        $this->registry = new IncrementStorageRegistry(
            new ServiceLocator([
                'SQL' => static fn () => $sqlStorage,
                'Array' => static fn () => $arrayStorage,
            ]),
            'SQL',
        );

        static::assertEmpty($sqlStorage->list($this->context));
        $this->registry->migrate('Array', 'SQL', $this->context);

        static::assertEquals($arrayStorage->list($this->context), $sqlStorage->list($this->context));
    }

    public function testMigrateFromSqlStorage(): void
    {
        $first = $this->createConfigAndState(10);
        $second = $this->createConfigAndState(4);
        $sqlStorage = static::getContainer()->get(IncrementSqlStorage::class);
        $sqlStorage->set($first, $this->context);
        $sqlStorage->set($second, $this->context);
        $arrayStorage = new IncrementArrayStorage([]);

        $this->registry = new IncrementStorageRegistry(
            new ServiceLocator([
                'SQL' => static fn () => $sqlStorage,
                'Array' => static fn () => $arrayStorage,
            ]),
            'SQL',
        );

        static::assertEmpty($arrayStorage->list($this->context));
        $this->registry->migrate('SQL', 'Array', $this->context);

        static::assertEquals($sqlStorage->list($this->context), $arrayStorage->list($this->context));
    }

    private function createConfigAndState(int $value): IncrementState
    {
        $id = Uuid::randomHex();
        $typeId = $this->connection->fetchOne('SELECT `id` FROM `number_range_type` LIMIT 1');
        static::assertIsString($typeId);
        $this->connection->insert('number_range', [
            'id' => Uuid::fromHexToBytes($id),
            'data_scope_id' => Uuid::fromHexToBytes($this->context->getDataScopeId()),
            'type_id' => $typeId,
            'global' => 1,
            'pattern' => '{n}',
            'start' => 1,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        return new IncrementState($this->context->getDataScopeId(), $id, $value);
    }
}
