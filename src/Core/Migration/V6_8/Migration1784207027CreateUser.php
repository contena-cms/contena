<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207027CreateUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207027;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user` (
    `tenant_id`                BINARY(16)                              NULL,
    `id`                       BINARY(16)                              NOT NULL,
    `user_code`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `platform_user_code`       VARCHAR(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `user_code`, NULL)) STORED,
    `username`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `platform_username`        VARCHAR(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `username`, NULL)) STORED,
    `password`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name`                     VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number`             VARCHAR(32)  COLLATE utf8mb4_unicode_ci NULL,
    `gender`                   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `email`                    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `platform_email`           VARCHAR(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `email`, NULL)) STORED,
    `active`                   TINYINT(1)                              NOT NULL DEFAULT 1,
    `admin`                    TINYINT(1)                              NULL,
    `first_login`              DATETIME(3)                             NULL,
    `last_login`               DATETIME(3)                             NULL,
    `last_updated_password_at` DATETIME(3)                             NULL,
    `time_zone`                VARCHAR(255)                            NOT NULL DEFAULT 'Asia/Shanghai',
    `avatar_id`                BINARY(16)                              NULL,
    `locale_id`                BINARY(16)                              NOT NULL,
    `custom_fields`            JSON                                    NULL,
    `created_at`               DATETIME(3)                             NOT NULL,
    `updated_at`               DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.user.tenant_id` (`tenant_id`),
    CONSTRAINT `uniq.user.tenant_email` UNIQUE (`tenant_id`, `email`),
    CONSTRAINT `uniq.user.tenant_user_code` UNIQUE (`tenant_id`, `user_code`),
    CONSTRAINT `uniq.user.tenant_username` UNIQUE (`tenant_id`, `username`),
    CONSTRAINT `uniq.user.platform_email` UNIQUE (`platform_email`),
    CONSTRAINT `uniq.user.platform_user_code` UNIQUE (`platform_user_code`),
    CONSTRAINT `uniq.user.platform_username` UNIQUE (`platform_username`),
    CONSTRAINT `json.user.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.user.locale_id` FOREIGN KEY (`locale_id`)
        REFERENCES `locale` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.user.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
