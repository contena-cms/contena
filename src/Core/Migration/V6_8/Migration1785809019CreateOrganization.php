<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;

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
    `tenant_id`     BINARY(16)                              NULL,
    `id`             BINARY(16)                              NOT NULL,
    `technical_name` VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `platform_technical_name` VARCHAR(64) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `technical_name`, NULL)) STORED,
    `position`       INT(11)                                 NOT NULL DEFAULT 1,
    `active`         TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields`  JSON                                    NULL,
    `created_at`     DATETIME(3)                             NOT NULL,
    `updated_at`     DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.organization_unit.tenant_technical_name` (`tenant_id`, `technical_name`),
    UNIQUE KEY `uniq.organization_unit.platform_technical_name` (`platform_technical_name`),
    KEY `idx.organization_unit.tenant_id` (`tenant_id`),
    KEY `idx.organization_unit.active_position` (`active`, `position`),
    CONSTRAINT `json.organization_unit.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.organization_unit.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization_unit_translation` (
    `tenant_id`            BINARY(16)                              NULL,
    `organization_unit_id` BINARY(16)                              NOT NULL,
    `language_id`          BINARY(16)                              NOT NULL,
    `name`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description`          LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3)                             NULL,
    PRIMARY KEY (`organization_unit_id`, `language_id`),
    KEY `idx.organization_unit_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.organization_unit_translation.organization_unit_id` FOREIGN KEY (`organization_unit_id`)
        REFERENCES `organization_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_unit_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_unit_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization` (
    `tenant_id`           BINARY(16)                              NULL,
    `id`                   BINARY(16)                              NOT NULL,
    `parent_id`            BINARY(16)                              NULL,
    `organization_unit_id` BINARY(16)                              NOT NULL,
    `level`                INT(11) UNSIGNED                        NOT NULL DEFAULT 1,
    `code`                 VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `platform_code`        VARCHAR(64) COLLATE utf8mb4_unicode_ci  GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `code`, NULL)) STORED,
    `path`                 LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `child_count`          INT(11)                                 NOT NULL DEFAULT 0,
    `position`             INT(11)                                 NOT NULL DEFAULT 1,
    `active`               TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields`        JSON                                    NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.organization.tenant_code` (`tenant_id`, `code`),
    UNIQUE KEY `uniq.organization.platform_code` (`platform_code`),
    KEY `idx.organization.tenant_id` (`tenant_id`),
    KEY `idx.organization.parent_id` (`parent_id`),
    KEY `idx.organization.unit_active` (`organization_unit_id`, `active`),
    KEY `idx.organization.level_active` (`level`, `active`),
    CONSTRAINT `json.organization.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.organization.parent_id` FOREIGN KEY (`parent_id`)
        REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization.organization_unit_id` FOREIGN KEY (`organization_unit_id`)
        REFERENCES `organization_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.organization.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `organization_translation` (
    `tenant_id`       BINARY(16)                              NULL,
    `organization_id` BINARY(16)                              NOT NULL,
    `language_id`     BINARY(16)                              NOT NULL,
    `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `short_name`      VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`organization_id`, `language_id`),
    KEY `idx.organization_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.organization_translation.organization_id` FOREIGN KEY (`organization_id`)
        REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.organization_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
