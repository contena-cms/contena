<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

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
    `id`                  BINARY(16)   NOT NULL,
    `tenant_id`           BINARY(16)   NULL,
    `configuration_key`   VARCHAR(255) NOT NULL,
    `configuration_value` JSON         NOT NULL,
    `channel_id`          BINARY(16)   NULL,
    `created_at`          DATETIME(3)  NOT NULL,
    `updated_at`          DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.system_config.configuration_value` CHECK (JSON_VALID(`configuration_value`)),
    CONSTRAINT `uniq.system_config.configuration_key` UNIQUE (`configuration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
