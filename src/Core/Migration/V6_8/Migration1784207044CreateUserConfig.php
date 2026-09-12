<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

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
    `data_scope_id`  BINARY(16)   NOT NULL,
    `id`         BINARY(16)   NOT NULL,
    `user_id`    BINARY(16)   NOT NULL,
    `key`        VARCHAR(255) NOT NULL,
    `value`      JSON         NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.user_config.data_scope_id` (`data_scope_id`),
    UNIQUE `uniq.user_id_key` (`user_id`, `key`),
    CONSTRAINT `json.user_config.value` CHECK (JSON_VALID(`value`)),
    CONSTRAINT `fk.user_config.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_config.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
