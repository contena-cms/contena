<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785336697McpToolResultCache;

/**
 * @internal
 */
#[CoversClass(Migration1785336697McpToolResultCache::class)]
class Migration1785336697McpToolResultCacheTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `mcp_tool_result_cache`');
    }

    public function testCreatesToolResultCacheTableIdempotently(): void
    {
        $migration = new Migration1785336697McpToolResultCache();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['id', 'session_id', 'mime_type', 'content', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_tool_result_cache', $column), $column);
        }
    }
}
