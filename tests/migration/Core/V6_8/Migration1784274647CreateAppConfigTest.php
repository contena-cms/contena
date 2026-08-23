<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784274647CreateAppConfig;

/**
 * @internal
 */
#[CoversClass(Migration1784274647CreateAppConfig::class)]
class Migration1784274647CreateAppConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_config`');
    }

    public function testCreatesAppConfigTableIdempotently(): void
    {
        $migration = new Migration1784274647CreateAppConfig();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app_config', 'key'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'app_config', 'value'));
    }
}
