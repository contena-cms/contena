<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207018CreateCustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207018;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field` (
    `id`                BINARY(16)   NOT NULL,
    `name`              VARCHAR(255) NOT NULL,
    `type`              VARCHAR(255) NOT NULL,
    `config`            JSON         NULL,
    `active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `set_id`            BINARY(16)   NULL,
    `channel_api_aware` TINYINT(1)  NOT NULL DEFAULT 1,
    `include_in_search` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`        DATETIME(3)  NOT NULL,
    `updated_at`        DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.custom_field.name` UNIQUE (`name`),
    CONSTRAINT `json.custom_field.config` CHECK (JSON_VALID(`config`)),
    CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (`set_id`)
        REFERENCES `custom_field_set` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
