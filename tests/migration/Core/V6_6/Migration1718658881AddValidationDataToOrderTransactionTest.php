<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_6\Migration1718658881AddValidationDataToOrderTransaction;

/**
 * @internal
 */
#[CoversClass(Migration1718658881AddValidationDataToOrderTransaction::class)]
class Migration1718658881AddValidationDataToOrderTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1718658881, (new Migration1718658881AddValidationDataToOrderTransaction())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $column = TableHelper::getColumnOfTable($this->connection, 'order_transaction', 'validation_data');
        static::assertFalse($column->isNotNull);
    }

    private function migrate(): void
    {
        (new Migration1718658881AddValidationDataToOrderTransaction())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `order_transaction` DROP COLUMN `validation_data`');
    }
}
