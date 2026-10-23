<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1782308630AddBusinessTimeZoneToChannel;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @internal
 */
#[CoversClass(Migration1782308630AddBusinessTimeZoneToChannel::class)]
class Migration1782308630AddBusinessTimeZoneToChannelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsNullableColumnWithoutBackfill(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $channelCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `channel`');
        static::assertGreaterThan(0, $channelCount);

        $migration = new Migration1782308630AddBusinessTimeZoneToChannel();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(ChannelDefinition::ENTITY_NAME)
            ->getColumn('business_time_zone');

        static::assertFalse($column->getNotnull());
        static::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `channel` WHERE `business_time_zone` IS NOT NULL')
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1782308630,
            (new Migration1782308630AddBusinessTimeZoneToChannel())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, ChannelDefinition::ENTITY_NAME, 'business_time_zone');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `channel` DROP COLUMN `business_time_zone`');
    }
}
