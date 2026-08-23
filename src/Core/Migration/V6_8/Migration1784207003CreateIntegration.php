<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207003CreateIntegration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207003;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `integration` (
    `tenant_id`        BINARY(16)   NULL,
    `id`                BINARY(16)   NOT NULL,
    `label`             VARCHAR(255) NOT NULL,
    `access_key`        VARCHAR(255) NOT NULL,
    `secret_access_key` VARCHAR(255) NOT NULL,
    `last_usage_at`     DATETIME(3)  NULL,
    `admin`             TINYINT(1)   NOT NULL DEFAULT 1,
    `custom_fields`     JSON         NULL,
    `deleted_at`        DATETIME(3)  NULL,
    `created_at`        DATETIME(3)  NOT NULL,
    `updated_at`        DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.integration.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.integration.access_key` (`access_key`),
    CONSTRAINT `json.integration.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.integration.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
