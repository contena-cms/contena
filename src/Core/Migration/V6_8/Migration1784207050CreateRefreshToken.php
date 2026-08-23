<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207050CreateRefreshToken extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207050;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `refresh_token` (
    `id`         BINARY(16)  NOT NULL,
    `user_id`    BINARY(16)  NOT NULL,
    `token_id`   VARCHAR(80) NOT NULL,
    `issued_at`  DATETIME(3) NOT NULL,
    `expires_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.refresh_token.token_id` UNIQUE (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
