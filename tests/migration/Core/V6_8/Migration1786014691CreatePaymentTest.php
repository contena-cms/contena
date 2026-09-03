<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1786014691CreatePayment;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1786014691CreatePayment::class)]
class Migration1786014691CreatePaymentTest extends TestCase
{
    private const array TABLES = [
        'payment_notify_record',
        'payment_channel_notify_record',
        'payment_order_transaction',
        'payment_transfer',
        'payment_refund',
        'payment_order',
        'payment_recurring',
        'payment_channel_config',
        'payment_app_channel_method',
        'payment_channel_method_translation',
        'payment_channel_method',
        'payment_channel_translation',
        'payment_channel',
        'payment_app_translation',
        'payment_app',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->dropPaymentTables();
    }

    public function testCreatesPaymentSchemaIdempotently(): void
    {
        $migration = new Migration1786014691CreatePayment();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
        }

        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_channel', 'config_schema'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_recurring', 'channel_config_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_recurring', 'channel_extra'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_recurring', 'response_data'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_transfer', 'channel_config_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_transfer', 'channel_extra'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_transfer', 'response_data'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_order', 'return_url'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_recurring', 'fk.payment_recurring.channel_config_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'payment_transfer', 'fk.payment_transfer.channel_config_id'));
    }

    private function dropPaymentTables(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
