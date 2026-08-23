<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
class Migration1785812508CreatePosition extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1785812508;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `position` (
    `tenant_id`     BINARY(16)                              NULL,
    `id`            BINARY(16)                              NOT NULL,
    `code`          VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `platform_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci  GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `code`, NULL)) STORED,
    `position`      INT(11)                                 NOT NULL DEFAULT 1,
    `active`        TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.position.tenant_code` (`tenant_id`, `code`),
    UNIQUE KEY `uniq.position.platform_code` (`platform_code`),
    KEY `idx.position.tenant_id` (`tenant_id`),
    KEY `idx.position.active_position` (`active`, `position`),
    CONSTRAINT `json.position.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.position.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `position_translation` (
    `tenant_id`  BINARY(16)                              NULL,
    `position_id` BINARY(16)                              NOT NULL,
    `language_id` BINARY(16)                              NOT NULL,
    `name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description` LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `created_at`  DATETIME(3)                             NOT NULL,
    `updated_at`  DATETIME(3)                             NULL,
    PRIMARY KEY (`position_id`, `language_id`),
    KEY `idx.position_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.position_translation.position_id` FOREIGN KEY (`position_id`)
        REFERENCES `position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.position_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.position_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_position` (
    `tenant_id`  BINARY(16) NULL,
    `user_id`     BINARY(16) NOT NULL,
    `position_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`user_id`, `position_id`),
    KEY `idx.user_position.position_id` (`position_id`),
    KEY `idx.user_position.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.user_position.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_position.position_id` FOREIGN KEY (`position_id`)
        REFERENCES `position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_position.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'user', 'title');
    }
}
