<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1776803396RegisterProductIndexer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1776803396RegisterProductIndexer::class)]
class Migration1776803396RegisterProductIndexerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1776803396RegisterProductIndexer();

        static::assertSame(1776803396, $migration->getCreationTimestamp());
    }

    public function testProductIndexerIsRegistered(): void
    {
        $migration = new Migration1776803396RegisterProductIndexer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('product.indexer', $indexers);
    }
}
