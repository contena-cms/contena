<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_8\Migration1743256470RemoveElasticsearchAppConfigFlag;

/**
 * @internal
 */
#[CoversClass(Migration1743256470RemoveElasticsearchAppConfigFlag::class)]
class Migration1743256470RemoveElasticsearchAppConfigFlagTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1743256470, (new Migration1743256470RemoveElasticsearchAppConfigFlag())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $storage = new MySQLKeyValueStorage($this->connection);

        $storage->set('ELASTIC_OPTIMIZE_FLAG', true);

        static::assertTrue($storage->has('ELASTIC_OPTIMIZE_FLAG'));
        $migration = new Migration1743256470RemoveElasticsearchAppConfigFlag();
        static::assertSame(1743256470, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);

        $storage->reset();
        static::assertFalse($storage->has('ELASTIC_OPTIMIZE_FLAG'));
    }
}
