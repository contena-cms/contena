<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1702479623RemoveEsMultilingualFlag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1702479623RemoveEsMultilingualFlag::class)]
class Migration1702479623RemoveEsMultilingualFlagTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1702479623, (new Migration1702479623RemoveEsMultilingualFlag())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $storage = new MySQLKeyValueStorage($this->connection);
        $storage->set('enable-multilingual-index', true);

        static::assertTrue($storage->has('enable-multilingual-index'));

        $migration = new Migration1702479623RemoveEsMultilingualFlag();
        $migration->update($this->connection);
        $storage->reset();
        static::assertFalse($storage->has('enable-multilingual-index'));

        $migration->update($this->connection);
        $storage->reset();
        static::assertFalse($storage->has('enable-multilingual-index'));
    }
}
