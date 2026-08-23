<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784279738CreateIncrement;

/**
 * @internal
 */
#[CoversClass(Migration1784279738CreateIncrement::class)]
class Migration1784279738CreateIncrementTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `increment`');
    }

    public function testCreatesIncrementTableIdempotently(): void
    {
        $migration = new Migration1784279738CreateIncrement();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'increment', 'pool'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'increment', 'cluster'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'increment', 'key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'increment', 'count'));
    }
}
