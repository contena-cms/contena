<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1786014691CreatePayment;
use Contena\Core\Migration\V6_8\Migration1788665786RemovePaymentTransactionPlaceholders;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1788665786RemovePaymentTransactionPlaceholders::class)]
final class Migration1788665786RemovePaymentTransactionPlaceholdersTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        new Migration1786014691CreatePayment()->update($this->connection);

        if (!TableHelper::columnExists($this->connection, 'payment_order_transaction', 'type')) {
            $this->connection->executeStatement('ALTER TABLE `payment_order_transaction` ADD COLUMN `type` VARCHAR(32) NOT NULL DEFAULT \'pay\'');
        } elseif (!TableHelper::getColumnOfTable($this->connection, 'payment_order_transaction', 'type')->isNotNull) {
            $this->connection->executeStatement('ALTER TABLE `payment_order_transaction` MODIFY COLUMN `type` VARCHAR(32) NOT NULL DEFAULT \'pay\'');
        }
        if (!TableHelper::columnExists($this->connection, 'payment_order_transaction', 'operator')) {
            $this->connection->executeStatement('ALTER TABLE `payment_order_transaction` ADD COLUMN `operator` VARCHAR(64) NULL');
        }
    }

    protected function tearDown(): void
    {
        new Migration1788665786RemovePaymentTransactionPlaceholders()->updateDestructive($this->connection);
    }

    public function testRemovesUnusedTransactionColumnsIdempotently(): void
    {
        $migration = new Migration1788665786RemovePaymentTransactionPlaceholders();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'payment_order_transaction', 'type')->isNotNull);

        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'payment_order_transaction', 'type'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'payment_order_transaction', 'operator'));
    }
}
