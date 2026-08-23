<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207051CreateDataDictionary extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207051;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `data_dictionary` (
    `id`             BINARY(16)                              NOT NULL,
    `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `active`         TINYINT(1)                              NOT NULL DEFAULT 1,
    `system_locked`  TINYINT(1)                              NOT NULL DEFAULT 0,
    `created_at`     DATETIME(3)                             NOT NULL,
    `updated_at`     DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.data_dictionary.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
