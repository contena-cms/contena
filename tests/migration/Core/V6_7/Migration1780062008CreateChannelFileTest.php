<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1780062008CreateChannelFile;

/**
 * @internal
 */
#[CoversClass(Migration1780062008CreateChannelFile::class)]
class Migration1780062008CreateChannelFileTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780062008, (new Migration1780062008CreateChannelFile())->getCreationTimestamp());
    }

    public function testMigrationCreatesTable(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::tableExists($this->connection, 'channel_file'));

        (new Migration1780062008CreateChannelFile())->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'channel_file'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1780062008CreateChannelFile();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'channel_file'));
    }

    public function testTableHasExpectedColumns(): void
    {
        $this->rollback();

        (new Migration1780062008CreateChannelFile())->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'channel_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'file_family'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'file_name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'enabled'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'template_overrides'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_file', 'updated_at'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `channel_file`');
    }
}
