<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

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
    `family_id`  BINARY(16)  NULL,
    `revoked_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.refresh_token.token_id` UNIQUE (`token_id`),
    INDEX `idx.refresh_token.family_id` (`family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `oauth_auth_code` (
    `id`         BINARY(16)   NOT NULL,
    `code_id`    VARCHAR(80)  NOT NULL,
    `user_id`    BINARY(16)   NOT NULL,
    `client_id`  VARCHAR(255) NOT NULL,
    `issued_at`  DATETIME(3)  NOT NULL,
    `expires_at` DATETIME(3)  NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.oauth_auth_code.code_id` UNIQUE (`code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `oauth_client` (
    `id`            BINARY(16)   NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `redirect_uris` JSON         NOT NULL,
    `created_at`    DATETIME(3)  NOT NULL,
    `updated_at`    DATETIME(3)  NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
