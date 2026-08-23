<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207044CreateUserConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207044;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_config` (
    `tenant_id`  BINARY(16)   NULL,
    `id`         BINARY(16)   NOT NULL,
    `user_id`    BINARY(16)   NOT NULL,
    `key`        VARCHAR(255) NOT NULL,
    `value`      JSON         NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.user_config.tenant_id` (`tenant_id`),
    UNIQUE `uniq.user_id_key` (`user_id`, `key`),
    CONSTRAINT `json.user_config.value` CHECK (JSON_VALID(`value`)),
    CONSTRAINT `fk.user_config.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_config.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
