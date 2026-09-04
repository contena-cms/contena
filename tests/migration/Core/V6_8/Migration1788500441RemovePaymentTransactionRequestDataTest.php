<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1786014691CreatePayment;
use Contena\Core\Migration\V6_8\Migration1788500441RemovePaymentTransactionRequestData;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1788500441RemovePaymentTransactionRequestData::class)]
final class Migration1788500441RemovePaymentTransactionRequestDataTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        new Migration1786014691CreatePayment()->update($this->connection);
        if (!TableHelper::columnExists($this->connection, 'payment_order_transaction', 'request_data')) {
            $this->connection->executeStatement('ALTER TABLE `payment_order_transaction` ADD COLUMN `request_data` JSON NULL AFTER `state_id`');
        }
    }

    protected function tearDown(): void
    {
        new Migration1788500441RemovePaymentTransactionRequestData()->update($this->connection);
    }

    public function testRemovesRequestDataIdempotently(): void
    {
        $migration = new Migration1788500441RemovePaymentTransactionRequestData();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'payment_order_transaction', 'request_data'));
    }
}
