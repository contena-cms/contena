<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207024CreateNumberRange extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207024;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range` (
    `id`         BINARY(16)   NOT NULL,
    `data_scope_id`  BINARY(16)   NOT NULL,
    `type_id`    BINARY(16)   NOT NULL,
    `global`     TINYINT(1)   NOT NULL,
    `pattern`    VARCHAR(255) NOT NULL,
    `start`      INTEGER(8)   NOT NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.number_range.data_scope_id_id` (`data_scope_id`, `id`),
    KEY `idx.number_range.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.number_range.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
