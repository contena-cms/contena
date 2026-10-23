<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1772007509BlogMainCategoryInheritance;

/**
 * @internal
 */
#[CoversClass(Migration1772007509BlogMainCategoryInheritance::class)]
class Migration1772007509BlogMainCategoryInheritanceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1772007509, (new Migration1772007509BlogMainCategoryInheritance())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1772007509BlogMainCategoryInheritance();

        static::assertSame(1772007509, $migration->getCreationTimestamp());
    }

    public function testMigrationCreatesMainCategoriesColumn(): void
    {
        $this->rollback();

        $migration = new Migration1772007509BlogMainCategoryInheritance();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'mainCategories'));
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, 'blog', 'mainCategories')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN `mainCategories`');
    }
}
