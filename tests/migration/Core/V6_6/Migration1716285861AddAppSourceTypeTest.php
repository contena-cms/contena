<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_6\Migration1716285861AddAppSourceType;

/**
 * @internal
 */
#[CoversClass(Migration1716285861AddAppSourceType::class)]
class Migration1716285861AddAppSourceTypeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1716285861, (new Migration1716285861AddAppSourceType())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropSourceTypeColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'source_type'));

        $migration = new Migration1716285861AddAppSourceType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app', 'source_type'));
    }

    private function dropSourceTypeColumn(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` DROP COLUMN `source_type`;'
            );
        } catch (\Throwable) {
        }
    }
}
