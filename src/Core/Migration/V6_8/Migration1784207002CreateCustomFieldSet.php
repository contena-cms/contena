<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207002CreateCustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field_set` (
    `id`             BINARY(16)   NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `config`         JSON         NULL,
    `active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `global`         TINYINT(1)   NOT NULL DEFAULT 0,
    `position`       INT(11)      NOT NULL DEFAULT 1,
    `extension_name` VARCHAR(255) NULL,
    `created_at`     DATETIME(3)  NOT NULL,
    `updated_at`     DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.custom_field_set.config` CHECK (JSON_VALID(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
