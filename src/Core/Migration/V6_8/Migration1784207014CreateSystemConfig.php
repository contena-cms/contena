<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207014CreateSystemConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207014;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `system_config` (
    `id`                      BINARY(16)   NOT NULL,
    `data_scope_id`           BINARY(16)   NOT NULL,
    `configuration_key`       VARCHAR(255) NOT NULL,
    `configuration_value`     JSON         NOT NULL,
    `channel_id`              BINARY(16)   NULL,
    `configuration_target_id` BINARY(16) GENERATED ALWAYS AS (COALESCE(`channel_id`, `data_scope_id`)) STORED,
    `created_at`              DATETIME(3)  NOT NULL,
    `updated_at`              DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.system_config.data_scope_id` (`data_scope_id`),
    CONSTRAINT `json.system_config.configuration_value` CHECK (JSON_VALID(`configuration_value`)),
    CONSTRAINT `uniq.system_config.scope_target_key` UNIQUE (`data_scope_id`, `configuration_target_id`, `configuration_key`),
    -- MySQL rejects foreign keys in this generated-target layout; DAL scope validation enforces the same boundary.
    KEY `idx.system_config.data_scope_id_fk` (`data_scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
