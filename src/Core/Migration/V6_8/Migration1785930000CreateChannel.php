<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * Development-baseline schema for Channel and MemberGroup.
 *
 * The tables are consolidated from the final upstream SalesChannel and CustomerGroup schema. Commerce-only
 * currency, tax, payment, shipping, product, order, and customer relations are intentionally omitted.
 *
 * @internal
 */
class Migration1785930000CreateChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785930000;
    }

    public function update(Connection $connection): void
    {
        $this->createMemberGroup($connection);
        $this->createChannelType($connection);
        $this->createChannelAnalytics($connection);
        $this->createChannel($connection);
        $this->createContentAssociations($connection);
        $this->createSeo($connection);
        $this->addChannelToSystemConfig($connection);
        $this->createChannelAssociations($connection);
        $this->createMember($connection);
        $this->createMemberAddress($connection);
        $this->createMemberRecovery($connection);
        $this->createMemberTag($connection);
        $this->createChannelDomain($connection);
        $this->createChannelFile($connection);
        $this->createChannelContext($connection);
        $this->createConsentStorage($connection);
        $this->addHreflangForeignKey($connection);
    }

    private function addChannelToSystemConfig(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'system_config', 'tenant_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD COLUMN `tenant_id` BINARY(16) NULL AFTER `id`
SQL);
        }

        if (!TableHelper::columnExists($connection, 'system_config', 'channel_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD COLUMN `channel_id` BINARY(16) NULL AFTER `configuration_value`
SQL);
        }

        if (TableHelper::indexExists($connection, 'system_config', 'uniq.system_config.configuration_key')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    DROP INDEX `uniq.system_config.configuration_key`
SQL);
        }

        if (TableHelper::indexExists($connection, 'system_config', 'uniq.system_config.configuration_key__channel_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    DROP INDEX `uniq.system_config.configuration_key__channel_id`
SQL);
        }

        if (!TableHelper::indexExists($connection, 'system_config', 'uniq.system_config.configuration_key__channel_id__tenant_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD CONSTRAINT `uniq.system_config.configuration_key__channel_id__tenant_id` UNIQUE (`configuration_key`, `channel_id`, `tenant_id`)
SQL);
        }

        if (!TableHelper::indexExists($connection, 'system_config', 'idx.system_config.tenant_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD KEY `idx.system_config.tenant_id` (`tenant_id`)
SQL);
        }

        if (!$this->foreignKeyExists($connection, 'system_config', 'fk.system_config.tenant_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD CONSTRAINT `fk.system_config.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
        }

        if (!$this->foreignKeyExists($connection, 'system_config', 'fk.system_config.channel_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `system_config`
    ADD CONSTRAINT `fk.system_config.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL);
        }
    }

    private function createMemberGroup(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_group` (
    `tenant_id`          BINARY(16)  NULL,
    `id`                  BINARY(16)  NOT NULL,
    `registration_active` TINYINT(1)  NOT NULL DEFAULT 0,
    `created_at`          DATETIME(3) NOT NULL,
    `updated_at`          DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.member_group.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.member_group.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_group_translation` (
    `tenant_id`                            BINARY(16)                              NULL,
    `member_group_id`                       BINARY(16)                              NOT NULL,
    `language_id`                           BINARY(16)                              NOT NULL,
    `name`                                  VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `registration_title`                    VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `registration_introduction`             LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `registration_seo_meta_description`      LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `custom_fields`                         JSON                                    NULL,
    `created_at`                            DATETIME(3)                             NOT NULL,
    `updated_at`                            DATETIME(3)                             NULL,
    PRIMARY KEY (`member_group_id`, `language_id`),
    KEY `idx.member_group_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `json.member_group_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.member_group_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_group_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.member_group_translation.member_group_id` FOREIGN KEY (`member_group_id`)
        REFERENCES `member_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelType(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_type` (
    `id`              BINARY(16)                              NOT NULL,
    `cover_url`       VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL,
    `icon_name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `screenshot_urls` JSON                                    NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.channel_type.screenshot_urls` CHECK (JSON_VALID(`screenshot_urls`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_type_translation` (
    `channel_type_id` BINARY(16)                              NOT NULL,
    `language_id`     BINARY(16)                              NOT NULL,
    `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `manufacturer`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description_long` LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `custom_fields`   JSON                                    NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`channel_type_id`, `language_id`),
    CONSTRAINT `json.channel_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.channel_type_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_type_translation.channel_type_id` FOREIGN KEY (`channel_type_id`)
        REFERENCES `channel_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannel(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel` (
    `id`                         BINARY(16)                              NOT NULL,
    `tenant_id`                  BINARY(16)                              NULL,
    `type_id`                    BINARY(16)                              NOT NULL,
    `short_name`                 VARCHAR(45) COLLATE utf8mb4_unicode_ci  NULL,
    `configuration`              JSON                                    NULL,
    `access_key`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `language_id`                BINARY(16)                              NOT NULL,
    `country_id`                 BINARY(16)                              NOT NULL,
    `member_group_id`            BINARY(16)                              NOT NULL,
    `navigation_category_id`     BINARY(16)                              NOT NULL,
    `navigation_category_version_id` BINARY(16)                          NOT NULL,
    `navigation_category_depth`  INT                                     NOT NULL DEFAULT 2,
    `footer_category_id`         BINARY(16)                              NULL,
    `footer_category_version_id` BINARY(16)                              NULL,
    `service_category_id`        BINARY(16)                              NULL,
    `service_category_version_id` BINARY(16)                             NULL,
    `mail_header_footer_id`      BINARY(16)                              NULL,
    `analytics_id`               BINARY(16)                              NULL,
    `active`                     TINYINT(1)                              NOT NULL DEFAULT 1,
    `maintenance`                TINYINT(1)                              NOT NULL DEFAULT 0,
    `maintenance_ip_allowlist`   JSON                                    NULL,
    `hreflang_active`            TINYINT(1)                              NOT NULL DEFAULT 0,
    `hreflang_default_domain_id` BINARY(16)                              NULL,
    `business_time_zone`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at`                 DATETIME(3)                             NOT NULL,
    `updated_at`                 DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.channel.access_key` (`access_key`),
    KEY `idx.channel.tenant_id` (`tenant_id`),
    CONSTRAINT `json.channel.configuration` CHECK (JSON_VALID(`configuration`)),
    CONSTRAINT `json.channel.maintenance_ip_allowlist` CHECK (JSON_VALID(`maintenance_ip_allowlist`)),
    CONSTRAINT `fk.channel.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.country_id` FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.member_group_id` FOREIGN KEY (`member_group_id`)
        REFERENCES `member_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.navigation_category_id` FOREIGN KEY (`navigation_category_id`, `navigation_category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.footer_category_id` FOREIGN KEY (`footer_category_id`, `footer_category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.service_category_id` FOREIGN KEY (`service_category_id`, `service_category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.type_id` FOREIGN KEY (`type_id`)
        REFERENCES `channel_type` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.mail_header_footer_id` FOREIGN KEY (`mail_header_footer_id`)
        REFERENCES `mail_header_footer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.channel.analytics_id` FOREIGN KEY (`analytics_id`)
        REFERENCES `channel_analytics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_translation` (
    `tenant_id`  BINARY(16)                              NULL,
    `channel_id`           BINARY(16)                              NOT NULL,
    `language_id`          BINARY(16)                              NOT NULL,
    `name`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `home_enabled`         TINYINT(1)                              NOT NULL DEFAULT 1,
    `home_name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `home_meta_title`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `home_meta_description` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `home_keywords`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields`        JSON                                    NULL,
    `created_at`           DATETIME(3)                             NOT NULL,
    `updated_at`           DATETIME(3)                             NULL,
    PRIMARY KEY (`channel_id`, `language_id`),
    KEY `idx.channel_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `json.channel_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.channel_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_translation.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createContentAssociations(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_channel` (
    `tenant_id`               BINARY(16) NULL,
    `landing_page_id`         BINARY(16) NOT NULL,
    `landing_page_version_id` BINARY(16) NOT NULL,
    `channel_id`              BINARY(16) NOT NULL,
    PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `channel_id`),
    KEY `idx.landing_page_channel.tenant_id` (`tenant_id`),
    KEY `fk.landing_page_channel.channel_id` (`channel_id`),
    CONSTRAINT `fk.landing_page_channel.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.landing_page_channel.landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
        REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.landing_page_channel.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_visibility` (
    `tenant_id`       BINARY(16)  NULL,
    `id`              BINARY(16)  NOT NULL,
    `blog_id`         BINARY(16)  NOT NULL,
    `blog_version_id` BINARY(16)  NOT NULL,
    `channel_id`      BINARY(16)  NOT NULL,
    `visibility`      INT         NOT NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.blog_visibility.blog_id__channel_id` (`blog_id`, `blog_version_id`, `channel_id`),
    KEY `idx.blog_visibility.tenant_id` (`tenant_id`),
    KEY `idx.blog_visibility.blog_id` (`blog_id`, `blog_version_id`),
    KEY `idx.blog_visibility.channel_id` (`channel_id`),
    CONSTRAINT `fk.blog_visibility.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_visibility.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_visibility.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_main_category` (
    `tenant_id`          BINARY(16)  NULL,
    `id`                  BINARY(16)  NOT NULL,
    `blog_id`             BINARY(16)  NOT NULL,
    `blog_version_id`     BINARY(16)  NOT NULL,
    `category_id`         BINARY(16)  NOT NULL,
    `category_version_id` BINARY(16)  NOT NULL,
    `channel_id`          BINARY(16)  NOT NULL,
    `created_at`          DATETIME(3) NOT NULL,
    `updated_at`          DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.blog_main_category.channel_blog` (`blog_id`, `blog_version_id`, `channel_id`),
    KEY `idx.blog_main_category.tenant_id` (`tenant_id`),
    KEY `fk.blog_main_category.channel_id` (`channel_id`),
    KEY `fk.blog_main_category.category_id` (`category_id`, `category_version_id`),
    CONSTRAINT `fk.blog_main_category.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_main_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_main_category.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_main_category.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        foreach (['blog', 'category', 'landing_page'] as $entity) {
            $table = $entity . '_content_layout';
            $entityId = $entity . '_id';
            $uniqueName = match ($entity) {
                'blog' => 'uniq.blog_content_layout.blog_channel',
                'category' => 'uniq.category_content_layout.category_channel',
                'landing_page' => 'uniq.landing_page_content_layout.landing_page_channel',
            };

            $this->executeDdlStatement($connection, str_replace(
                ['#table#', '#entity_id#', '#unique#'],
                [$table, $entityId, $uniqueName],
                <<<'SQL'
CREATE TABLE IF NOT EXISTS `#table#` (
    `id`                BINARY(16)  NOT NULL,
    `tenant_id`         BINARY(16)  NULL,
    `#entity_id#`       BINARY(16)  NOT NULL,
    `channel_id`        BINARY(16)  NULL,
    `content_layout_id` BINARY(16)  NOT NULL,
    `created_at`        DATETIME(3) NOT NULL,
    `updated_at`        DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `#unique#` (`#entity_id#`, `channel_id`),
    KEY `idx.#table#.tenant_id` (`tenant_id`),
    KEY `fk.#table#.channel_id` (`channel_id`),
    KEY `fk.#table#.content_layout_id` (`content_layout_id`),
    CONSTRAINT `fk.#table#.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.#table#.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.#table#.content_layout_id` FOREIGN KEY (`content_layout_id`)
        REFERENCES `content_layout` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            ));
        }
    }

    private function createSeo(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `seo_url` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`             BINARY(16)                               NOT NULL,
    `language_id`    BINARY(16)                               NOT NULL,
    `channel_id`     BINARY(16)                               NULL,
    `foreign_key`    BINARY(16)                               NOT NULL,
    `route_name`     VARCHAR(50) COLLATE utf8mb4_unicode_ci   NOT NULL,
    `path_info`      VARCHAR(750) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `seo_path_info`  VARCHAR(750) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `is_canonical`   TINYINT(1)                               NULL,
    `is_modified`    TINYINT(1)                               NOT NULL DEFAULT 0,
    `is_deleted`     TINYINT(1)                               NOT NULL DEFAULT 0,
    `custom_fields`  JSON                                     NULL,
    `created_at`     DATETIME(3)                              NOT NULL,
    `updated_at`     DATETIME(3)                              NULL,
    PRIMARY KEY (`id`),
    KEY `idx.seo_url.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.seo_url.seo_path_info` (`language_id`, `channel_id`, `seo_path_info`),
    UNIQUE KEY `uniq.seo_url.foreign_key` (`language_id`, `channel_id`, `foreign_key`, `route_name`, `is_canonical`),
    KEY `idx.seo_url.foreign_key` (`language_id`, `foreign_key`, `channel_id`, `is_canonical`),
    KEY `idx.seo_url.path_info` (`language_id`, `channel_id`, `is_canonical`, `path_info`),
    KEY `idx.seo_url.delete_query` (`foreign_key`, `channel_id`),
    KEY `fk.seo_url.channel_id` (`channel_id`),
    CONSTRAINT `fk.seo_url.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.seo_url.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.seo_url.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.seo_url.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `seo_url_template` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`            BINARY(16)                              NOT NULL,
    `channel_id`    BINARY(16)                              NULL,
    `route_name`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `entity_name`   VARCHAR(64) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `template`      VARCHAR(750) COLLATE utf8mb4_unicode_ci NULL,
    `is_valid`      TINYINT(1)                              NOT NULL DEFAULT 1,
    `is_headless`   TINYINT(1)                              NOT NULL DEFAULT 0,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.seo_url_template.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.seo_url_template.route_name` (`channel_id`, `route_name`),
    CONSTRAINT `fk.seo_url_template.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.seo_url_template.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.seo_url_template.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelAnalytics(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_analytics` (
    `id`           BINARY(16)                              NOT NULL,
    `tenant_id`    BINARY(16)                              NULL,
    `tracking_id`  VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `active`       TINYINT(1)                              NOT NULL DEFAULT 0,
    `anonymize_ip` TINYINT(1)                              NOT NULL DEFAULT 0,
    `created_at`   DATETIME(3)                             NOT NULL,
    `updated_at`   DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.channel_analytics.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.channel_analytics.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelAssociations(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_language` (
    `tenant_id`   BINARY(16) NULL,
    `channel_id`  BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`channel_id`, `language_id`),
    KEY `idx.channel_language.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.channel_language.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_language.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_language.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_country` (
    `tenant_id` BINARY(16) NULL,
    `channel_id` BINARY(16) NOT NULL,
    `country_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`channel_id`, `country_id`),
    KEY `idx.channel_country.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.channel_country.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_country.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_country.country_id` FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_group_registration_channel` (
    `tenant_id`      BINARY(16) NULL,
    `member_group_id` BINARY(16) NOT NULL,
    `channel_id`      BINARY(16) NOT NULL,
    PRIMARY KEY (`member_group_id`, `channel_id`),
    KEY `idx.member_group_registration_channel.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.member_group_registration_channel.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_group_registration_channel.member_group_id` FOREIGN KEY (`member_group_id`)
        REFERENCES `member_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.member_group_registration_channel.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelDomain(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_domain` (
    `id`                       BINARY(16)                              NOT NULL,
    `tenant_id`                BINARY(16)                              NULL,
    `channel_id`               BINARY(16)                              NOT NULL,
    `language_id`              BINARY(16)                              NOT NULL,
    `url`                      VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `snippet_set_id`           BINARY(16)                              NOT NULL,
    `hreflang_use_only_locale` TINYINT(1)                              NOT NULL DEFAULT 0,
    `is_external_frontend`   TINYINT(1)                              NOT NULL DEFAULT 0,
    `external_frontend_language_id` BINARY(16)
        GENERATED ALWAYS AS (IF(`is_external_frontend` = 1, `language_id`, NULL)) VIRTUAL,
    `custom_fields`            JSON                                    NULL,
    `created_at`               DATETIME(3)                             NOT NULL,
    `updated_at`               DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.channel_domain.url` (`url`),
    UNIQUE KEY `uniq.channel_domain.external_frontend` (`external_frontend_language_id`, `channel_id`),
    KEY `idx.channel_domain.tenant_id` (`tenant_id`),
    CONSTRAINT `json.channel_domain.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.channel_domain.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_domain.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_domain.language_id` FOREIGN KEY (`channel_id`, `language_id`)
        REFERENCES `channel_language` (`channel_id`, `language_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_domain.snippet_set_id` FOREIGN KEY (`snippet_set_id`)
        REFERENCES `snippet_set` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelFile(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_file` (
    `id`                 BINARY(16)   NOT NULL,
    `tenant_id`          BINARY(16)   NULL,
    `channel_id`         BINARY(16)   NOT NULL,
    `file_family`        VARCHAR(64)  NOT NULL,
    `file_name`          VARCHAR(512) NOT NULL,
    `enabled`            TINYINT(1)   NOT NULL DEFAULT 0,
    `template_overrides` JSON         NULL,
    `created_at`         DATETIME(3)  NOT NULL,
    `updated_at`         DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.channel_file.channel_id_family_file_name` (`channel_id`, `file_family`, `file_name`),
    KEY `idx.channel_file.tenant_id` (`tenant_id`),
    CONSTRAINT `json.channel_file.template_overrides` CHECK (JSON_VALID(`template_overrides`)),
    CONSTRAINT `fk.channel_file.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_file.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createMember(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member` (
    `id`                           BINARY(16)                              NOT NULL,
    `tenant_id`                    BINARY(16)                              NULL,
    `auto_increment`               BIGINT UNSIGNED                         NOT NULL AUTO_INCREMENT,
    `member_group_id`              BINARY(16)                              NOT NULL,
    `channel_id`                   BINARY(16)                              NOT NULL,
    `language_id`                  BINARY(16)                              NOT NULL,
    `member_number`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name`                         VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number`                 VARCHAR(32) COLLATE utf8mb4_unicode_ci  NULL,
    `password`                     VARCHAR(1024) COLLATE utf8mb4_unicode_ci NULL,
    `email`                        VARCHAR(254) COLLATE utf8mb4_unicode_ci NOT NULL,
    `title`                        VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
    `active`                       TINYINT(1)                              NOT NULL DEFAULT 1,
    `double_opt_in_registration`   TINYINT(1)                              NOT NULL DEFAULT 0,
    `double_opt_in_email_sent_date` DATETIME(3)                             NULL,
    `double_opt_in_confirm_date`   DATETIME(3)                             NULL,
    `hash`                         VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `first_login`                  DATETIME(3)                             NULL,
    `last_login`                   DATETIME(3)                             NULL,
    `birthday`                     DATE                                    NULL,
    `tag_ids`                      JSON                                    NULL,
    `custom_fields`                JSON                                    NULL,
    `remote_address`               VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `requested_member_group_id`    BINARY(16)                              NULL,
    `created_by_id`                BINARY(16)                              NULL,
    `updated_by_id`                BINARY(16)                              NULL,
    `created_at`                   DATETIME(3)                             NOT NULL,
    `updated_at`                   DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.member.auto_increment` (`auto_increment`),
    KEY `idx.member.email` (`email`),
    KEY `idx.member.member_number` (`member_number`),
    KEY `idx.member.tenant_id` (`tenant_id`),
    CONSTRAINT `json.member.tag_ids` CHECK (JSON_VALID(`tag_ids`)),
    CONSTRAINT `json.member.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.member.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member.member_group_id` FOREIGN KEY (`member_group_id`)
        REFERENCES `member_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member.requested_member_group_id` FOREIGN KEY (`requested_member_group_id`)
        REFERENCES `member_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createMemberRecovery(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_recovery` (
    `id`         BINARY(16)                              NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `member_id`  BINARY(16)                              NOT NULL,
    `hash`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.member_recovery.member_id` (`member_id`),
    KEY `idx.member_recovery.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.member_recovery.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_recovery.member_id` FOREIGN KEY (`member_id`)
        REFERENCES `member` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createMemberAddress(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_address` (
    `id`                         BINARY(16)                              NOT NULL,
    `tenant_id`                  BINARY(16)                              NULL,
    `member_id`                  BINARY(16)                              NOT NULL,
    `country_id`                 BINARY(16)                              NOT NULL,
    `region_id`                  BINARY(16)                              NULL,
    `first_name`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_name`                  VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `zipcode`                    VARCHAR(50) COLLATE utf8mb4_unicode_ci  NULL,
    `city`                       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `title`                      VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
    `street`                     VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone_number`               VARCHAR(40) COLLATE utf8mb4_unicode_ci  NULL,
    `additional_address_line1`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `additional_address_line2`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields`              JSON                                    NULL,
    `created_at`                 DATETIME(3)                             NOT NULL,
    `updated_at`                 DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.member_address.tenant_id` (`tenant_id`),
    CONSTRAINT `json.member_address.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.member_address.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_address.member_id` FOREIGN KEY (`member_id`)
        REFERENCES `member` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.member_address.country_id` FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_address.region_id` FOREIGN KEY (`region_id`)
        REFERENCES `region` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createMemberTag(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `member_tag` (
    `tenant_id` BINARY(16) NULL,
    `member_id`  BINARY(16) NOT NULL,
    `tag_id`     BINARY(16) NOT NULL,
    PRIMARY KEY (`member_id`, `tag_id`),
    KEY `idx.member_tag.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.member_tag.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.member_tag.member_id` FOREIGN KEY (`member_id`)
        REFERENCES `member` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.member_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createChannelContext(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS channel_api_context (
    token       VARCHAR(32)  NOT NULL,
    payload     JSON         NOT NULL,
    channel_id  BINARY(16)   NOT NULL,
    member_id   BINARY(16)   NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (token),
    KEY idx_channel_api_context_channel_id (channel_id),
    KEY idx_channel_api_context_member_id (member_id),
    CONSTRAINT json_channel_api_context_payload CHECK (JSON_VALID(payload)),
    CONSTRAINT fk_channel_api_context_channel_id FOREIGN KEY (channel_id)
        REFERENCES channel (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_channel_api_context_member_id FOREIGN KEY (member_id)
        REFERENCES member (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createConsentStorage(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `consent_state` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `identifier` VARCHAR(100) NOT NULL,
    `state` VARCHAR(20) NOT NULL,
    `actor` VARCHAR(255) NOT NULL,
    `updated_at` DATETIME(3) NOT NULL,
    `revision` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.consent_state` (`name`, `identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `consent_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    `consent_name` VARCHAR(100) NOT NULL,
    `timestamp` DATETIME(3) NOT NULL,
    `message` LONGTEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx.consent_log.history` (`consent_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cookie_consent_log` (
    `id` BINARY(16) NOT NULL,
    `tenant_id` BINARY(16) NULL,
    `channel_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `consent_action` VARCHAR(32) NOT NULL,
    `accepted_groups` JSON NOT NULL,
    `config_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.cookie_consent_log.tenant_id` (`tenant_id`),
    KEY `idx.cookie_consent_log.created_at` (`created_at`),
    KEY `idx.cookie_consent_log.config_hash` (`config_hash`),
    CONSTRAINT `json.cookie_consent_log.accepted_groups` CHECK (JSON_VALID(`accepted_groups`)),
    CONSTRAINT `fk.cookie_consent_log.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cookie_consent_config_version` (
    `id` BINARY(16) NOT NULL,
    `tenant_id` BINARY(16) NULL,
    `config_hash` VARCHAR(255) NOT NULL,
    `channel_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `cookie_groups` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.cookie_consent_config_version.config_hash` (`tenant_id`, `config_hash`, `channel_id`, `language_id`),
    KEY `idx.cookie_consent_config_version.tenant_id` (`tenant_id`),
    CONSTRAINT `json.cookie_consent_config_version.cookie_groups` CHECK (JSON_VALID(`cookie_groups`)),
    CONSTRAINT `fk.cookie_consent_config_version.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function addHreflangForeignKey(Connection $connection): void
    {
        if ($this->foreignKeyExists($connection, 'channel', 'fk.channel.hreflang_default_domain_id')) {
            return;
        }

        $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `channel`
    ADD CONSTRAINT `fk.channel.hreflang_default_domain_id`
    FOREIGN KEY (`hreflang_default_domain_id`)
    REFERENCES `channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL);
    }
}
