<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207035CreateNumberRangeState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207035;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_state` (
    `id`            BINARY(16) NOT NULL,
    `data_scope_id` BINARY(16) NOT NULL,
    `number_range_id` BINARY(16) NOT NULL,
    `last_value`    INTEGER(8) NOT NULL,
    `created_at`    DATETIME(3) NOT NULL,
    `updated_at`    DATETIME(3) NULL,
    PRIMARY KEY (`data_scope_id`, `number_range_id`),
    UNIQUE `uniq.id` (`id`),
    INDEX `idx.number_range_id` (`number_range_id`),
    INDEX `idx.number_range_state.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.number_range_state.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.number_range_state.number_range_scope` FOREIGN KEY (`data_scope_id`, `number_range_id`)
        REFERENCES `number_range` (`data_scope_id`, `id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
