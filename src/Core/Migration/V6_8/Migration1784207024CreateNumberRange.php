<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

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
    `tenant_id`  BINARY(16)   NULL,
    `type_id`    BINARY(16)   NOT NULL,
    `global`     TINYINT(1)   NOT NULL,
    `pattern`    VARCHAR(255) NOT NULL,
    `start`      INTEGER(8)   NOT NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.number_range.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.number_range.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
