<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1784207014CreateSystemConfig;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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

    public function testCreatesDataScopedSystemConfigTableIdempotently(): void
    {
        $migration = new Migration1784207014CreateSystemConfig();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'configuration_key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'configuration_value'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'data_scope_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'channel_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'configuration_target_id'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'system_config', 'tenant_id'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'system_config', 'scope_type'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'system_config', 'scope_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'system_config', 'uniq.system_config.scope_target_key'));
    }

    public function testRejectsDuplicateScopeWideKeys(): void
    {
        new Migration1784207014CreateSystemConfig()->update($this->connection);

        $row = [
            'id' => Uuid::randomBytes(),
            'data_scope_id' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
            'configuration_key' => 'duplicate.scope.key',
            'configuration_value' => '{"_value":true}',
            'channel_id' => null,
            'created_at' => '2026-01-01 00:00:00.000',
        ];
        $this->connection->insert('system_config', $row);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection->insert('system_config', [
            ...$row,
            'id' => Uuid::randomBytes(),
        ]);
    }
}
