<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785335178AddMcpAllowlist;

/**
 * @internal
 */
#[CoversClass(Migration1785335178AddMcpAllowlist::class)]
class Migration1785335178AddMcpAllowlistTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        foreach (['integration', 'user'] as $table) {
            if (TableHelper::columnExists($this->connection, $table, 'mcp_allowlist')) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP COLUMN `mcp_allowlist`', $table));
            }
        }
    }

    public function testAddsNullableJsonAllowlistColumnsIdempotently(): void
    {
        $migration = new Migration1785335178AddMcpAllowlist();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['integration', 'user'] as $table) {
            $column = TableHelper::getColumnOfTable($this->connection, $table, 'mcp_allowlist');
            static::assertSame('json', $column->type);
            static::assertFalse($column->isNotNull);
        }
    }
}
