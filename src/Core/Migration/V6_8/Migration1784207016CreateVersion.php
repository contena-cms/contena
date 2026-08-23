<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207016CreateVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207016;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `version` (
    `id`         BINARY(16)                              NOT NULL,
    `name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.version.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
