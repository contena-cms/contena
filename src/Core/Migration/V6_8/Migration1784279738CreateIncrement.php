<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784279738CreateIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784279738;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `increment` (
    `pool`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `cluster`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `key`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `count`      BIGINT UNSIGNED                         NOT NULL DEFAULT 1,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`pool`, `cluster`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
