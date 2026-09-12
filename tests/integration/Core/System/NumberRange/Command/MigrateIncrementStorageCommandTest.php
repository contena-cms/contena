<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\NumberRange\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Contena\Core\System\Tenant\DataScopeContextProvider;
use Contena\Core\Test\Stub\System\NumberRange\ValueGenerator\IncrementArrayStorage;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
class MigrateIncrementStorageCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementSqlStorage $sqlStorage;

    private IncrementArrayStorage $arrayStorage;

    private CommandTester $tester;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->sqlStorage = static::getContainer()->get(IncrementSqlStorage::class);
        $this->arrayStorage = new IncrementArrayStorage([]);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `number_range_state`');

        $command = new MigrateIncrementStorageCommand(
            new IncrementStorageRegistry(new ServiceLocator([
                'SQL' => fn () => $this->sqlStorage,
                'Array' => fn () => $this->arrayStorage,
            ]), 'SQL'),
            static::getContainer()->get(DataScopeContextProvider::class),
        );

        $this->tester = new CommandTester($command);
    }

    public function testMigrateWithConfirmation(): void
    {
        $numberRangeId = $this->existingPlatformNumberRangeId();
        $platformContext = Context::createDefaultContext();
        $this->sqlStorage->set(new IncrementState($platformContext->getDataScopeId(), $numberRangeId, 10), $platformContext);
        static::assertNotEmpty($this->sqlStorage->list($platformContext));
        $before = $this->arrayStorage->list($platformContext);
        static::assertEmpty($before);

        $this->tester->setInputs(['yes']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        $this->tester->assertCommandIsSuccessful();

        $after = $this->arrayStorage->list($platformContext);
        static::assertNotEmpty($after);
        static::assertSame($this->sqlStorage->list($platformContext), $this->arrayStorage->list($platformContext));
    }

    public function testMigrateWithUserAbort(): void
    {
        $numberRangeId = $this->existingPlatformNumberRangeId();
        $platformContext = Context::createDefaultContext();
        $this->sqlStorage->set(new IncrementState($platformContext->getDataScopeId(), $numberRangeId, 10), $platformContext);
        static::assertNotEmpty($this->sqlStorage->list($platformContext));
        static::assertEmpty($this->arrayStorage->list($platformContext));

        $this->tester->setInputs(['no']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        static::assertSame(Command::FAILURE, $this->tester->getStatusCode());

        static::assertEmpty($this->arrayStorage->list($platformContext));
    }

    private function existingPlatformNumberRangeId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `number_range` WHERE `data_scope_id` = :scope LIMIT 1',
            ['scope' => Uuid::fromHexToBytes(Context::createDefaultContext()->getDataScopeId())],
        );
        static::assertIsString($id);

        return $id;
    }
}
