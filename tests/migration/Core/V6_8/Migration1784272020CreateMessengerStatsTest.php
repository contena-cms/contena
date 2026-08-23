<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784272020CreateMessengerStats;

/**
 * @internal
 */
#[CoversClass(Migration1784272020CreateMessengerStats::class)]
class Migration1784272020CreateMessengerStatsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `messenger_stats`');
    }

    public function testCreatesMessengerStatsTableIdempotently(): void
    {
        $migration = new Migration1784272020CreateMessengerStats();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'message_type'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'time_in_queue'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_stats', 'created_at'));
    }
}
