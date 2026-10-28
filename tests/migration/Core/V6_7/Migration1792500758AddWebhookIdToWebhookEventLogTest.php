<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1792500758AddWebhookIdToWebhookEventLog;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792500758AddWebhookIdToWebhookEventLog::class)]
class Migration1792500758AddWebhookIdToWebhookEventLogTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792500758, (new Migration1792500758AddWebhookIdToWebhookEventLog())->getCreationTimestamp());
    }

    public function testMigrationAddsTheColumnWithAnIndexAndAForeignKey(): void
    {
        if (TableHelper::foreignKeyExists($this->connection, 'webhook_event_log', 'fk.webhook_event_log.webhook_id')) {
            $this->connection->executeStatement('ALTER TABLE `webhook_event_log` DROP FOREIGN KEY `fk.webhook_event_log.webhook_id`');
        }
        if (TableHelper::columnExists($this->connection, 'webhook_event_log', 'webhook_id')) {
            $this->connection->executeStatement('ALTER TABLE `webhook_event_log` DROP COLUMN `webhook_id`');
        }

        $migration = new Migration1792500758AddWebhookIdToWebhookEventLog();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook_event_log', 'webhook_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'webhook_event_log', 'idx.webhook_event_log.webhook_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'webhook_event_log', 'fk.webhook_event_log.webhook_id'));
    }
}
