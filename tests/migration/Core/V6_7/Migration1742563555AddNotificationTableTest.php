<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1742563555AddNotificationTable;

/**
 * @internal
 */
#[CoversClass(Migration1742563555AddNotificationTable::class)]
class Migration1742563555AddNotificationTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `notification`;');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1742563555, (new Migration1742563555AddNotificationTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'notification'));

        $migration = new Migration1742563555AddNotificationTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'notification'));

        static::assertCount(9, TableHelper::getTable($this->connection, 'notification')->columns);
        $messageColumn = TableHelper::getColumnOfTable($this->connection, 'notification', 'message');
        static::assertSame(Types::TEXT, $messageColumn->type);
    }
}
