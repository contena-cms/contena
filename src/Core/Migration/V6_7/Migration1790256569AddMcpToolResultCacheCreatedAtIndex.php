<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1790256569AddMcpToolResultCacheCreatedAtIndex extends MigrationStep
{
    private const INDEX_NAME = 'idx.mcp_tool_result_cache.created_at';

    public function getCreationTimestamp(): int
    {
        return 1790256569;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'mcp_tool_result_cache', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `' . self::INDEX_NAME . '` ON `mcp_tool_result_cache` (`created_at`)'
        );
    }
}
