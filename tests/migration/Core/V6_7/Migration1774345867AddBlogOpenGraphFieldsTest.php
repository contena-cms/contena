<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1774345867AddBlogOpenGraphFields;

/**
 * @internal
 */
#[CoversClass(Migration1774345867AddBlogOpenGraphFields::class)]
class Migration1774345867AddBlogOpenGraphFieldsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1774345867, (new Migration1774345867AddBlogOpenGraphFields())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1774345867AddBlogOpenGraphFields();

        static::assertSame(1774345867, $migration->getCreationTimestamp());
    }

    public function testColumnsAndForeignKeyAreCreated(): void
    {
        $this->rollbackInheritanceColumn();

        $migration = new Migration1774345867AddBlogOpenGraphFields();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'open_graph_media_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'openGraphMedia'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'blog_translation', 'og_title'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'blog_translation', 'og_description'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'fk.blog.open_graph_media_id'));
    }

    public function testForeignKeyIsCreatedWhenMissing(): void
    {
        $migration = new Migration1774345867AddBlogOpenGraphFields();
        $migration->update($this->connection);

        if (TableHelper::foreignKeyExists($this->connection, 'blog', 'fk.blog.open_graph_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `blog` DROP FOREIGN KEY `fk.blog.open_graph_media_id`');
        }

        if (TableHelper::indexExists($this->connection, 'blog', 'fk.blog.open_graph_media_id')) {
            $this->connection->executeStatement('ALTER TABLE `blog` DROP INDEX `fk.blog.open_graph_media_id`');
        }

        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'fk.blog.open_graph_media_id'));
    }

    private function rollbackInheritanceColumn(): void
    {
        if (!TableHelper::columnExists($this->connection, 'blog', 'openGraphMedia')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN `openGraphMedia`');
    }
}
