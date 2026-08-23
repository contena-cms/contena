<?php declare(strict_types=1);

namespace Contena\Frontend\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * Development-baseline schema for the Frontend Theme aggregate.
 *
 * Add future Theme tables and columns to this step while the baseline remains unreleased instead of preserving
 * upstream's historical migration chain.
 *
 * @internal
 */
class Migration1785943081CreateTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785943081;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme` (
    `id`               BINARY(16)                              NOT NULL,
    `technical_name`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `name`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `author`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `preview_media_id` BINARY(16)                              NULL,
    `parent_theme_id`  BINARY(16)                              NULL,
    `theme_json`       JSON                                    NULL,
    `base_config`      JSON                                    NULL,
    `config_values`    JSON                                    NULL,
    `active`           TINYINT(1)                              NOT NULL DEFAULT 1,
    `created_at`       DATETIME(3)                             NOT NULL,
    `updated_at`       DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.theme.technical_name` (`technical_name`),
    CONSTRAINT `json.theme.theme_json` CHECK (JSON_VALID(`theme_json`)),
    CONSTRAINT `json.theme.base_config` CHECK (JSON_VALID(`base_config`)),
    CONSTRAINT `json.theme.config_values` CHECK (JSON_VALID(`config_values`)),
    CONSTRAINT `fk.theme.preview_media_id` FOREIGN KEY (`preview_media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_channel` (
    `theme_id`   BINARY(16) NOT NULL,
    `channel_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`channel_id`),
    CONSTRAINT `fk.theme_channel.theme_id` FOREIGN KEY (`theme_id`)
        REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.theme_channel.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->createSectionContentLayouts($connection);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_translation` (
    `theme_id`     BINARY(16)                              NOT NULL,
    `language_id`  BINARY(16)                              NOT NULL,
    `description`  MEDIUMTEXT COLLATE utf8mb4_unicode_ci  NULL,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`theme_id`, `language_id`),
    CONSTRAINT `json.theme_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.theme_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.theme_translation.theme_id` FOREIGN KEY (`theme_id`)
        REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_media` (
    `theme_id` BINARY(16) NOT NULL,
    `media_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`theme_id`, `media_id`),
    CONSTRAINT `fk.theme_media.theme_id` FOREIGN KEY (`theme_id`)
        REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.theme_media.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_child` (
    `parent_id` BINARY(16) NOT NULL,
    `child_id`  BINARY(16) NOT NULL,
    PRIMARY KEY (`parent_id`, `child_id`),
    CONSTRAINT `fk.theme_child.parent_id__theme_id` FOREIGN KEY (`parent_id`)
        REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.theme_child.child_id` FOREIGN KEY (`child_id`)
        REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_runtime_config` (
    `theme_id`         BINARY(16)   NOT NULL,
    `technical_name`   VARCHAR(255) NULL,
    `resolved_config`  JSON         NOT NULL,
    `view_inheritance` JSON         NOT NULL,
    `script_files`     JSON         NULL,
    `icon_sets`        JSON         NOT NULL,
    `import_map`       JSON         NULL,
    `updated_at`       DATETIME(3)  NOT NULL,
    PRIMARY KEY (`theme_id`),
    UNIQUE KEY `uidx.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->createDefaultMediaFolder($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createDefaultMediaFolder(Connection $connection): void
    {
        $defaultFolderId = $connection->fetchOne(
            'SELECT `id` FROM `media_default_folder` WHERE `entity` = :entity',
            ['entity' => 'theme']
        );

        if ($defaultFolderId === false) {
            $defaultFolderId = Uuid::randomBytes();
            $connection->insert('media_default_folder', [
                'id' => $defaultFolderId,
                'entity' => 'theme',
                'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($connection->fetchOne(
            'SELECT 1 FROM `media_folder` WHERE `default_folder_id` = :defaultFolderId',
            ['defaultFolderId' => $defaultFolderId]
        ) !== false) {
            return;
        }

        $createdAt = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $configurationId = Uuid::randomBytes();
        $connection->insert('media_folder_configuration', [
            'id' => $configurationId,
            'created_at' => $createdAt,
        ]);
        $connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'name' => 'Theme Media',
            'default_folder_id' => $defaultFolderId,
            'media_folder_configuration_id' => $configurationId,
            'use_parent_configuration' => 0,
            'child_count' => 0,
            'created_at' => $createdAt,
        ]);
    }

    private function createSectionContentLayouts(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `header_content_layout` (
    `tenant_id`        BINARY(16) NULL,
    `id`               BINARY(16) NOT NULL,
    `domain_id`        BINARY(16) NULL,
    `channel_id`       BINARY(16) NULL,
    `content_layout_id` BINARY(16) NOT NULL,
    `created_at`       DATETIME(3) NOT NULL,
    `updated_at`       DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.header_content_layout.domain_channel` (`domain_id`, `channel_id`),
    KEY `idx.header_content_layout.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.header_content_layout.tenant_id`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.header_content_layout.domain_id`
        FOREIGN KEY (`domain_id`) REFERENCES `channel_domain` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.header_content_layout.channel_id`
        FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.header_content_layout.content_layout_id`
        FOREIGN KEY (`content_layout_id`) REFERENCES `content_layout` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk.header_content_layout.domain_requires_channel`
        CHECK (`domain_id` IS NULL OR `channel_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `footer_content_layout` (
    `tenant_id`        BINARY(16) NULL,
    `id`               BINARY(16) NOT NULL,
    `domain_id`        BINARY(16) NULL,
    `channel_id`       BINARY(16) NULL,
    `content_layout_id` BINARY(16) NOT NULL,
    `created_at`       DATETIME(3) NOT NULL,
    `updated_at`       DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.footer_content_layout.domain_channel` (`domain_id`, `channel_id`),
    KEY `idx.footer_content_layout.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.footer_content_layout.tenant_id`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.footer_content_layout.domain_id`
        FOREIGN KEY (`domain_id`) REFERENCES `channel_domain` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.footer_content_layout.channel_id`
        FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.footer_content_layout.content_layout_id`
        FOREIGN KEY (`content_layout_id`) REFERENCES `content_layout` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk.footer_content_layout.domain_requires_channel`
        CHECK (`domain_id` IS NULL OR `channel_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
