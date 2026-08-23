<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785335179CreateMcpToolsetSession;

/**
 * @internal
 */
#[CoversClass(Migration1785335179CreateMcpToolsetSession::class)]
class Migration1785335179CreateMcpToolsetSessionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `mcp_toolset_session`');
    }

    public function testCreatesToolsetSessionTableIdempotently(): void
    {
        $migration = new Migration1785335179CreateMcpToolsetSession();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['session_id', 'toolset_name', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'mcp_toolset_session', $column), $column);
        }
    }
}
