<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

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
    `id`              BINARY(16) NOT NULL,
    `number_range_id` BINARY(16) NOT NULL,
    `last_value`      INTEGER(8) NOT NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`number_range_id`),
    UNIQUE `uniq.id` (`id`),
    INDEX `idx.number_range_id` (`number_range_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
