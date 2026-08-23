<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785335179CreateMcpToolsetSession extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785335179;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `mcp_toolset_session` (
                `session_id` VARCHAR(255) NOT NULL,
                `toolset_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`session_id`, `toolset_name`),
                KEY `idx_mcp_toolset_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );
    }
}
