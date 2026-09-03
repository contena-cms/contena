<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1788425391AddPaymentNotifyResponseStatus;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1788425391AddPaymentNotifyResponseStatus::class)]
final class Migration1788425391AddPaymentNotifyResponseStatusTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        if (TableHelper::columnExists($this->connection, 'payment_notify_record', 'response_status')) {
            $this->connection->executeStatement('ALTER TABLE `payment_notify_record` DROP COLUMN `response_status`');
        }
    }

    protected function tearDown(): void
    {
        new Migration1788425391AddPaymentNotifyResponseStatus()->update($this->connection);
    }

    public function testAddsResponseStatusIdempotently(): void
    {
        $migration = new Migration1788425391AddPaymentNotifyResponseStatus();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'payment_notify_record', 'response_status'));
    }
}
