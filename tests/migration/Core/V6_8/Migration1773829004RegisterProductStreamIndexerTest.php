<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_8\Migration1773829004RegisterProductStreamIndexer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1773829004RegisterProductStreamIndexer::class)]
class Migration1773829004RegisterProductStreamIndexerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773829004, (new Migration1773829004RegisterProductStreamIndexer())->getCreationTimestamp());
    }

    public function testRuleIndexerIsRegistered(): void
    {
        $migration = new Migration1773829004RegisterProductStreamIndexer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('product_stream.indexer', $indexers);
    }
}
