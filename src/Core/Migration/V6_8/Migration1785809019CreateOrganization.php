<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1785809019CreateOrganization extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1785809019;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization_unit` (
    `data_scope_id`     BINARY(16)                              NOT NULL,
    `id`             BINARY(16)                              NOT NULL,
    `technical_name` VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `position`       INT(11)                                 NOT NULL DEFAULT 1,
    `active`         TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields`  JSON                                    NULL,
    `created_at`     DATETIME(3)                             NOT NULL,
    `updated_at`     DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.organization_unit.scope_technical_name` (`data_scope_id`, `technical_name`),
    KEY `idx.organization_unit.data_scope_id` (`data_scope_id`),
    KEY `idx.organization_unit.active_position` (`active`, `position`),
    CONSTRAINT `json.organization_unit.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.organization_unit.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization_unit_translation` (
    `data_scope_id`            BINARY(16)                              NOT NULL,
    `organization_unit_id` BINARY(16)                              NOT NULL,
    `language_id`          BINARY(16)                              NOT NULL,
    `name`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description`          LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3)                             NULL,
    PRIMARY KEY (`organization_unit_id`, `language_id`),
    KEY `idx.organization_unit_translation.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.organization_unit_translation.organization_unit_id` FOREIGN KEY (`organization_unit_id`)
        REFERENCES `organization_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_unit_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_unit_translation.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization` (
    `data_scope_id`           BINARY(16)                              NOT NULL,
    `id`                   BINARY(16)                              NOT NULL,
    `parent_id`            BINARY(16)                              NULL,
    `organization_unit_id` BINARY(16)                              NOT NULL,
    `level`                INT(11) UNSIGNED                        NOT NULL DEFAULT 1,
    `code`                 VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `path`                 LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `child_count`          INT(11)                                 NOT NULL DEFAULT 0,
    `position`             INT(11)                                 NOT NULL DEFAULT 1,
    `active`               TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields`        JSON                                    NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.organization.scope_code` (`data_scope_id`, `code`),
    KEY `idx.organization.data_scope_id` (`data_scope_id`),
    KEY `idx.organization.parent_id` (`parent_id`),
    KEY `idx.organization.unit_active` (`organization_unit_id`, `active`),
    KEY `idx.organization.level_active` (`level`, `active`),
    CONSTRAINT `json.organization.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.organization.parent_id` FOREIGN KEY (`parent_id`)
        REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization.organization_unit_id` FOREIGN KEY (`organization_unit_id`)
        REFERENCES `organization_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.organization.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization_translation` (
    `data_scope_id`       BINARY(16)                              NOT NULL,
    `organization_id` BINARY(16)                              NOT NULL,
    `language_id`     BINARY(16)                              NOT NULL,
    `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `short_name`      VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`organization_id`, `language_id`),
    KEY `idx.organization_translation.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.organization_translation.organization_id` FOREIGN KEY (`organization_id`)
        REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_translation.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
