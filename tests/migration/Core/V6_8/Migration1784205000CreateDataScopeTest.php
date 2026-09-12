<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1784205000CreateDataScope;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1784205000CreateDataScope::class)]
class Migration1784205000CreateDataScopeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesCanonicalPlatformScopeIdempotently(): void
    {
        $migration = new Migration1784205000CreateDataScope();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'data_scope'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'data_scope', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'data_scope', 'type'));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `data_scope` WHERE `id` = :id AND `type` = :type',
            [
                'id' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
                'type' => 'platform',
            ],
        ));
    }
}
