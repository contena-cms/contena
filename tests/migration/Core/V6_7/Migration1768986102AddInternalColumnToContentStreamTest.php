<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1768986102AddInternalColumnToBlogStream;

/**
 * @internal
 */
#[CoversClass(Migration1768986102AddInternalColumnToBlogStream::class)]
class Migration1768986102AddInternalColumnToBlogStreamTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1768986102, (new Migration1768986102AddInternalColumnToBlogStream())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1768986102AddInternalColumnToBlogStream();
        static::assertSame(1768986102, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $migration = new Migration1768986102AddInternalColumnToBlogStream();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'content_stream', 'internal'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'content_stream', 'internal')) {
            $this->connection->executeStatement('ALTER TABLE `content_stream` DROP COLUMN `internal`;');
        }
    }
}
