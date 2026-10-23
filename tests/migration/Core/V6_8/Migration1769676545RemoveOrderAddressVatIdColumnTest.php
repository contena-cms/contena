<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1769676545RemoveOrderAddressVatIdColumn;

/**
 * @internal
 */
#[CoversClass(Migration1769676545RemoveOrderAddressVatIdColumn::class)]
class Migration1769676545RemoveOrderAddressVatIdColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1769676545, (new Migration1769676545RemoveOrderAddressVatIdColumn())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesVatIdColumn(): void
    {
        $this->ensureVatIdColumnExists();

        $migration = new Migration1769676545RemoveOrderAddressVatIdColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'order_address', 'vat_id'));
    }

    private function ensureVatIdColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'order_address', 'vat_id')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `order_address` ADD COLUMN `vat_id` VARCHAR(50) NULL');
    }
}
