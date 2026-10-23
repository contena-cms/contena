<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1756305375AddCategoriesIndexToBlog;

/**
 * @internal
 */
#[CoversClass(Migration1756305375AddCategoriesIndexToBlog::class)]
class Migration1756305375AddCategoriesIndexToBlogTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1756305375, (new Migration1756305375AddCategoriesIndexToBlog())->getCreationTimestamp());
    }

    public function testIndexIsCreated(): void
    {
        if (TableHelper::indexExists($this->connection, 'blog', 'idx.blog.categories')) {
            $this->connection->executeStatement('DROP INDEX `idx.blog.categories` ON `blog`');
        }

        $migration = new Migration1756305375AddCategoriesIndexToBlog();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'idx.blog.categories'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1756305375AddCategoriesIndexToBlog();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'idx.blog.categories'));
    }
}
