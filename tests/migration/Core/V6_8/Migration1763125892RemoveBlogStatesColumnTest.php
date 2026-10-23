<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1763125892RemoveBlogStatesColumn;

/**
 * @internal
 */
#[CoversClass(Migration1763125892RemoveBlogStatesColumn::class)]
class Migration1763125892RemoveBlogStatesColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763125892, (new Migration1763125892RemoveBlogStatesColumn())->getCreationTimestamp());
    }

    public function testRuleIndexerIsRegistered(): void
    {
        $migration = new Migration1763125892RemoveBlogStatesColumn();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        static::assertArrayHasKey('rule.indexer', $indexers);
    }

    public function testUpdateDestructiveDropsStatesColumn(): void
    {
        $this->addStatesColumn();

        $migration = new Migration1763125892RemoveBlogStatesColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'blog', 'states'));
    }

    private function addStatesColumn(): void
    {
        if (TableHelper::columnExists($this->connection, 'blog', 'states')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `blog` ADD COLUMN `states` JSON NULL');
        $this->connection->executeStatement('ALTER TABLE `blog` ADD CONSTRAINT `json.blog.states` CHECK (JSON_VALID(`states`))');
    }
}
