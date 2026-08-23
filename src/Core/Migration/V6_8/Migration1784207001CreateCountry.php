<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207001CreateCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `country` (
    `id`                                 BINARY(16)                              NOT NULL,
    `iso`                                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `position`                           INT(11)                                 NOT NULL DEFAULT 1,
    `active`                             TINYINT(1)                              NOT NULL DEFAULT 1,
    `iso3`                               VARCHAR(45) COLLATE utf8mb4_unicode_ci  NULL,
    `created_at`                         DATETIME(3)                             NOT NULL,
    `updated_at`                         DATETIME(3)                             NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
