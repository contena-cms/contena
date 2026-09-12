<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207045CreateUserRecovery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207045;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_recovery` (
    `id`         BINARY(16)   NOT NULL,
    `user_id`    BINARY(16)   NOT NULL,
    `hash`       VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3)  NOT NULL,
    `updated_at` DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.user_recovery.user_id` UNIQUE (`user_id`),
    CONSTRAINT `fk.user_recovery.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
