<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1779176196AddFeedLabelToBlogExport;

/**
 * @internal
 */
#[CoversClass(Migration1779176196AddFeedLabelToBlogExport::class)]
class Migration1779176196AddFeedLabelToBlogExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779176196, (new Migration1779176196AddFeedLabelToBlogExport())->getCreationTimestamp());
    }

    public function testMigrationAddsFeedLabelColumnIdempotently(): void
    {
        $this->dropFeedLabelColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'blog_export', 'feed_label'));

        $migration = new Migration1779176196AddFeedLabelToBlogExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog_export', 'feed_label'));
    }

    private function dropFeedLabelColumn(): void
    {
        try {
            $this->connection->executeStatement('ALTER TABLE `blog_export` DROP COLUMN `feed_label`;');
        } catch (\Throwable) {
        }
    }
}
