<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1773824493AddProviderColumnToProductExport;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773824493AddProviderColumnToProductExport::class)]
class Migration1773824493AddProviderColumnToProductExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773824493, (new Migration1773824493AddProviderColumnToProductExport())->getCreationTimestamp());
    }

    public function testMigrationAddsProviderColumn(): void
    {
        $this->dropProviderColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'product_export', 'provider'));

        $migration = new Migration1773824493AddProviderColumnToProductExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product_export', 'provider'));
    }

    private function dropProviderColumn(): void
    {
        try {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `provider`;');
        } catch (\Throwable) {
        }
    }
}
