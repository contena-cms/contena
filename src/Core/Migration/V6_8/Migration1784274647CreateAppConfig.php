<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784274647CreateAppConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784274647;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_config` (
    `key`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` LONGTEXT COLLATE utf8mb4_unicode_ci     NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
