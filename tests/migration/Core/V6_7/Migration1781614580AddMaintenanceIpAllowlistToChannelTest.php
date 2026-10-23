<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1781614580AddMaintenanceIpAllowlistToChannel;

/**
 * @internal
 */
#[CoversClass(Migration1781614580AddMaintenanceIpAllowlistToChannel::class)]
class Migration1781614580AddMaintenanceIpAllowlistToChannelTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1781614580AddMaintenanceIpAllowlistToChannel $migration;

    private string $channelId;

    private ?string $originalAllowlist = null;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->migration = new Migration1781614580AddMaintenanceIpAllowlistToChannel();

        $id = $this->connection->fetchOne(<<<'SQL'
            SELECT id FROM `channel` LIMIT 1
        SQL);
        static::assertIsString($id);
        $this->channelId = $id;
        $this->originalAllowlist = $this->connection->fetchOne(
            <<<'SQL'
                SELECT maintenance_ip_allowlist FROM `channel` WHERE id = :id
            SQL,
            ['id' => $this->channelId]
        ) ?: null;
    }

    #[After]
    public function cleanUp(): void
    {
        // make sure the new column exists again for the remaining test suite
        $this->migration->update($this->connection);

        $this->connection->update(
            'channel',
            [
                'maintenance_ip_allowlist' => $this->originalAllowlist,
                'maintenance_ip_whitelist' => $this->originalAllowlist,
            ],
            ['id' => $this->channelId]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781614580, $this->migration->getCreationTimestamp());
    }

    public function testMigrationAddsColumnAndCopiesData(): void
    {
        $this->rollback();

        static::assertFalse(TableHelper::columnExists($this->connection, 'channel', 'maintenance_ip_allowlist'));

        $this->connection->update(
            'channel',
            ['maintenance_ip_whitelist' => json_encode(['127.0.0.1', '::1'], \JSON_THROW_ON_ERROR)],
            ['id' => $this->channelId]
        );

        // running twice must be idempotent
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'channel', 'maintenance_ip_allowlist'));

        $allowlist = $this->connection->fetchOne(
            <<<'SQL'
                SELECT maintenance_ip_allowlist FROM `channel` WHERE id = :id
            SQL,
            ['id' => $this->channelId]
        );

        static::assertIsString($allowlist);
        static::assertSame(['127.0.0.1', '::1'], json_decode($allowlist, true, 512, \JSON_THROW_ON_ERROR));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'channel', 'maintenance_ip_allowlist')) {
            $this->connection->executeStatement(<<<'SQL'
                ALTER TABLE `channel` DROP COLUMN `maintenance_ip_allowlist`
            SQL);
        }
    }
}
