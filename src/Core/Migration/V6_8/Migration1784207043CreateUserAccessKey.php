<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207043CreateUserAccessKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207043;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_access_key` (
    `tenant_id`        BINARY(16)   NULL,
    `id`                BINARY(16)   NOT NULL,
    `user_id`           BINARY(16)   NOT NULL,
    `access_key`        VARCHAR(255) NOT NULL,
    `secret_access_key` VARCHAR(255) NOT NULL,
    `last_usage_at`     DATETIME(3)  NULL,
    `custom_fields`     JSON         NULL,
    `created_at`        DATETIME(3)  NOT NULL,
    `updated_at`        DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    INDEX `idx.user_access_key.tenant_id` (`tenant_id`),
    INDEX `idx.user_access_key.user_id_` (`user_id`),
    UNIQUE KEY `uniq.user_access_key.access_key` (`access_key`),
    CONSTRAINT `json.user_access_key.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.user_access_key.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_access_key.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
