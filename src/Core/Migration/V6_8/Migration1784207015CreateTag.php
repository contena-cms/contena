<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207015CreateTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207015;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tag` (
    `id`         BINARY(16)                              NOT NULL,
    `data_scope_id`  BINARY(16)                              NOT NULL,
    `name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.tag.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.tag.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
