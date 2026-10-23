<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1775180400AddNextGenerationAtToBlogExport;

/**
 * @internal
 */
#[CoversClass(Migration1775180400AddNextGenerationAtToBlogExport::class)]
class Migration1775180400AddNextGenerationAtToBlogExportTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('ALTER TABLE `blog_export` DROP COLUMN `next_generation_at`;');
        } catch (\Throwable) {
        }
    }

    public function testMigrationAddsNextGenerationAtColumn(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'blog_export', 'next_generation_at'));

        $migration = new Migration1775180400AddNextGenerationAtToBlogExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog_export', 'next_generation_at'));
    }
}
