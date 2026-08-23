<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207028CreateVersionCommit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207028;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `version_commit` (
    `id`             BINARY(16)    NOT NULL,
    `auto_increment` BIGINT        NOT NULL AUTO_INCREMENT UNIQUE,
    `is_merge`       TINYINT(1)    NOT NULL DEFAULT 0,
    `message`        VARCHAR(5000) NULL,
    `user_id`        BINARY(16)    NULL,
    `integration_id` BINARY(16)    NULL,
    `version_id`     BINARY(16)    NOT NULL,
    `created_at`     DATETIME(3)   NOT NULL,
    `updated_at`     DATETIME(3)   NULL,
    PRIMARY KEY (`id`),
    INDEX `idx.version_commit.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
