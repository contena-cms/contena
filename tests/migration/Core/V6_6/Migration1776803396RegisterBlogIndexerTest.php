<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1776803396RegisterBlogIndexer;

/**
 * @internal
 */
#[CoversClass(Migration1776803396RegisterBlogIndexer::class)]
class Migration1776803396RegisterBlogIndexerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1776803396RegisterBlogIndexer();

        static::assertSame(1776803396, $migration->getCreationTimestamp());
    }

    public function testBlogIndexerIsRegistered(): void
    {
        $migration = new Migration1776803396RegisterBlogIndexer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('blog.indexer', $indexers);
    }
}
