<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1737472122TokenUser;

/**
 * @internal
 */
#[CoversClass(Migration1737472122TokenUser::class)]
class Migration1737472122TokenUserTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1737472122, (new Migration1737472122TokenUser())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1737472122TokenUser();
        static::assertSame(1737472122, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        if (TableHelper::tableExists($this->connection, 'oauth_user')) {
            $this->dropTable();
        }

        static::assertFalse(TableHelper::tableExists($this->connection, 'oauth_user'));

        $migration = new Migration1737472122TokenUser();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'oauth_user'));
    }

    public function dropTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `oauth_user`');
    }
}
