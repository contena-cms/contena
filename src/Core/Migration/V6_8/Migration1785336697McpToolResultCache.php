<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785336697McpToolResultCache extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785336697;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `mcp_tool_result_cache` (
                `id` BINARY(16) NOT NULL,
                `session_id` VARCHAR(255) NOT NULL,
                `mime_type` VARCHAR(127) NOT NULL DEFAULT 'application/json',
                `content` LONGTEXT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_mcp_tool_result_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }
}
