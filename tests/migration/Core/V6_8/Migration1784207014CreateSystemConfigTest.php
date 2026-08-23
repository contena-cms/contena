<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207014CreateSystemConfig;

/**
 * @internal
 */
#[CoversClass(Migration1784207014CreateSystemConfig::class)]
class Migration1784207014CreateSystemConfigTest extends TestCase
{
    private const string BACKUP_TABLE = 'system_config_migration_test_backup';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::BACKUP_TABLE . '`');
        $this->connection->executeStatement('RENAME TABLE `system_config` TO `' . self::BACKUP_TABLE . '`');
        $this->connection->executeStatement('ALTER TABLE `' . self::BACKUP_TABLE . '` DROP CHECK `json.system_config.configuration_value`');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `system_config`');
        $this->connection->executeStatement('RENAME TABLE `' . self::BACKUP_TABLE . '` TO `system_config`');
        $this->connection->executeStatement('ALTER TABLE `system_config` ADD CONSTRAINT `json.system_config.configuration_value` CHECK (JSON_VALID(`configuration_value`))');
    }

    public function testCreatesGlobalSystemConfigTableIdempotently(): void
    {
        $migration = new Migration1784207014CreateSystemConfig();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'configuration_key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'configuration_value'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'tenant_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'channel_id'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'system_config', 'scope_type'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'system_config', 'scope_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'system_config', 'uniq.system_config.configuration_key'));
    }
}
