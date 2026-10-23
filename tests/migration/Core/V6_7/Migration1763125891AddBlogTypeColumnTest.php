<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1763125891AddBlogTypeColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125891AddBlogTypeColumn::class)]
class Migration1763125891AddBlogTypeColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763125891, (new Migration1763125891AddBlogTypeColumn())->getCreationTimestamp());
    }

    public function testUpdateAddsTypeColumnAndIndex(): void
    {
        $this->ensureStatesColumnExists();
        $this->dropTypeColumnIfExists();

        $migration = new Migration1763125891AddBlogTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $typeColumn = TableHelper::getColumnOfTable($this->connection, 'blog', 'type');
        static::assertSame('physical', $typeColumn->defaultValue);
        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'idx.blog.type'));
    }

    public function testUpdateAddsMissingIndexWhenTypeColumnAlreadyExists(): void
    {
        $this->ensureStatesColumnExists();
        $this->ensureTypeColumnExistsWithoutIndex();

        $migration = new Migration1763125891AddBlogTypeColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $typeColumn = TableHelper::getColumnOfTable($this->connection, 'blog', 'type');
        static::assertSame('physical', $typeColumn->defaultValue);
        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'idx.blog.type'));
    }

    private function dropTypeColumnIfExists(): void
    {
        if (TableHelper::indexExists($this->connection, 'blog', 'idx.blog.type')) {
            $this->connection->executeStatement('DROP INDEX `idx.blog.type` ON `blog`');
        }

        if (TableHelper::columnExists($this->connection, 'blog', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN `type`');
        }
    }

    private function ensureStatesColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'blog', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `blog` ADD COLUMN `states` JSON NULL');
    }

    private function ensureTypeColumnExistsWithoutIndex(): void
    {
        $this->dropTypeColumnIfExists();

        $this->connection->executeStatement(
            'ALTER TABLE `blog` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT \'physical\''
        );
    }
}
