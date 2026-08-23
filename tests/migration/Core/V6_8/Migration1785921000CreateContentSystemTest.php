<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785921000CreateContentSystem;

/**
 * @internal
 */
#[CoversClass(Migration1785921000CreateContentSystem::class)]
class Migration1785921000CreateContentSystemTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `content_layout`');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCreatesContentSystemBaselineIdempotently(): void
    {
        $migration = new Migration1785921000CreateContentSystem();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'content_layout'));

        foreach (['id', 'name', 'version', 'layout', 'root_source', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', $column), $column);
        }

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'type'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'content_layout', 'uniq.content_layout.name_version'));
        static::assertSame('UNIQUE', TableHelper::getIndexOfTable($this->connection, 'content_layout', 'uniq.content_layout.name_version')->type);
    }
}
