<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * Development-baseline schema for the Content System aggregate.
 *
 * Add future Content System tables and columns to this step while the baseline remains unreleased instead of
 * creating one migration per table or incremental field change.
 *
 * @internal
 */
class Migration1785921000CreateContentSystem extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1785921000;
    }

    public function update(Connection $connection): void
    {
        $this->createContentLayout($connection);
        $this->createCategory($connection);
        $this->createLandingPage($connection);
        $this->createBlog($connection);
        $this->createBlogSorting($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createContentLayout(Connection $connection): void
    {
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `content_layout` (
    `tenant_id`   BINARY(16)                               NULL,
    `id`          BINARY(16)                               NOT NULL,
    `name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `version`     VARCHAR(20) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `layout`      JSON                                     NOT NULL,
    `root_source` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at`  DATETIME(3)                              NOT NULL,
    `updated_at`  DATETIME(3)                              NULL,
    PRIMARY KEY (`id`),
    UNIQUE `uniq.content_layout.name_version` (`name`, `version`),
    KEY `idx.content_layout.tenant_id` (`tenant_id`),
    CONSTRAINT `json.content_layout.layout` CHECK (JSON_VALID(`layout`)),
    CONSTRAINT `fk.content_layout.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createCategory(Connection $connection): void
    {
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `category` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`                        BINARY(16)                              NOT NULL,
    `version_id`                BINARY(16)                              NOT NULL,
    `auto_increment`            INT                                     NOT NULL AUTO_INCREMENT,
    `parent_id`                 BINARY(16)                              NULL,
    `parent_version_id`         BINARY(16)                              NULL,
    `media_id`                  BINARY(16)                              NULL,
    `path`                      LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `after_category_id`         BINARY(16)                              NULL,
    `after_category_version_id` BINARY(16)                              NULL,
    `level`                     INT UNSIGNED                            NOT NULL DEFAULT 1,
    `active`                    TINYINT(1)                              NOT NULL DEFAULT 1,
    `child_count`               INT UNSIGNED                            NOT NULL DEFAULT 0,
    `visible`                   TINYINT UNSIGNED                        NOT NULL DEFAULT 1,
    `type`                      VARCHAR(32) COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT 'page',
    `created_at`                DATETIME(3)                             NOT NULL,
    `updated_at`                DATETIME(3)                             NULL,
    PRIMARY KEY (`id`, `version_id`),
    KEY `idx.category.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.category.auto_increment` (`auto_increment`),
    KEY `idx.category.level` (`level`),
    KEY `fk.category.media_id` (`media_id`),
    KEY `fk.category.parent_id` (`parent_id`, `parent_version_id`),
    KEY `fk.category.after_category_id` (`after_category_id`, `after_category_version_id`),
    CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.category.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.category.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `category_translation` (
    `tenant_id`  BINARY(16)                              NULL,
    `category_id`         BINARY(16)                              NOT NULL,
    `category_version_id` BINARY(16)                              NOT NULL,
    `language_id`         BINARY(16)                              NOT NULL,
    `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `breadcrumb`          JSON                                    NULL,
    `internal_link`       BINARY(16)                              NULL,
    `link_new_tab`        TINYINT(1)                              NULL,
    `link_type`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `external_link`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description`         LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `meta_title`          LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `meta_description`    LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `keywords`            LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `custom_fields`       JSON                                    NULL,
    `created_at`          DATETIME(3)                             NOT NULL,
    `updated_at`          DATETIME(3)                             NULL,
    PRIMARY KEY (`category_id`, `category_version_id`, `language_id`),
    KEY `idx.category_translation.tenant_id` (`tenant_id`),
    KEY `fk.category_translation.language_id` (`language_id`),
    CONSTRAINT `fk.category_translation.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.category_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.category_translation.breadcrumb` CHECK (JSON_VALID(`breadcrumb`)),
    CONSTRAINT `json.category_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.category_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `category_tag` (
    `tenant_id`          BINARY(16) NULL,
    `category_id`         BINARY(16) NOT NULL,
    `category_version_id` BINARY(16) NOT NULL,
    `tag_id`              BINARY(16) NOT NULL,
    PRIMARY KEY (`category_id`, `category_version_id`, `tag_id`),
    KEY `idx.category_tag.tenant_id` (`tenant_id`),
    KEY `fk.category_tag.tag_id` (`tag_id`),
    CONSTRAINT `fk.category_tag.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.category_tag.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.category_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createLandingPage(Connection $connection): void
    {
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`         BINARY(16)  NOT NULL,
    `version_id` BINARY(16)  NOT NULL,
    `active`     TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`, `version_id`),
    KEY `idx.landing_page.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.landing_page.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_translation` (
    `tenant_id`  BINARY(16)                              NULL,
    `landing_page_id`         BINARY(16)                              NOT NULL,
    `landing_page_version_id` BINARY(16)                              NOT NULL,
    `language_id`             BINARY(16)                              NOT NULL,
    `name`                    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `url`                     VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `meta_title`              LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `meta_description`        LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `keywords`                LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `custom_fields`           JSON                                    NULL,
    `created_at`              DATETIME(3)                             NOT NULL,
    `updated_at`              DATETIME(3)                             NULL,
    PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `language_id`),
    KEY `idx.landing_page_translation.tenant_id` (`tenant_id`),
    KEY `fk.landing_page_translation.language_id` (`language_id`),
    CONSTRAINT `fk.landing_page_translation.landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
        REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.landing_page_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.landing_page_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.landing_page_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_tag` (
    `tenant_id`               BINARY(16) NULL,
    `landing_page_id`         BINARY(16) NOT NULL,
    `landing_page_version_id` BINARY(16) NOT NULL,
    `tag_id`                  BINARY(16) NOT NULL,
    PRIMARY KEY (`landing_page_id`, `landing_page_version_id`, `tag_id`),
    KEY `idx.landing_page_tag.tenant_id` (`tenant_id`),
    KEY `fk.landing_page_tag.tag_id` (`tag_id`),
    CONSTRAINT `fk.landing_page_tag.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.landing_page_tag.landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`)
        REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.landing_page_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createBlog(Connection $connection): void
    {
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`                    BINARY(16)                              NOT NULL,
    `version_id`            BINARY(16)                              NOT NULL,
    `auto_increment`        INT                                     NOT NULL AUTO_INCREMENT,
    `active`                TINYINT(1)                              NOT NULL DEFAULT 1,
    `type`                  VARCHAR(32) COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT 'post',
    `blog_media_id`         BINARY(16)                              NULL,
    `blog_media_version_id` BINARY(16)                              NULL,
    `open_graph_media_id`   BINARY(16)                              NULL,
    `category_tree`         JSON                                    NULL,
    `category_ids`          JSON                                    NULL,
    `tag_ids`               JSON                                    NULL,
    `release_date`          DATETIME(3)                             NULL,
    `created_at`            DATETIME(3)                             NOT NULL,
    `updated_at`            DATETIME(3)                             NULL,
    PRIMARY KEY (`id`, `version_id`),
    KEY `idx.blog.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.blog.auto_increment` (`auto_increment`),
    KEY `fk.blog.blog_media_id` (`blog_media_id`, `blog_media_version_id`),
    KEY `fk.blog.open_graph_media_id` (`open_graph_media_id`),
    CONSTRAINT `fk.blog.open_graph_media_id` FOREIGN KEY (`open_graph_media_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `json.blog.category_tree` CHECK (JSON_VALID(`category_tree`)),
    CONSTRAINT `json.blog.category_ids` CHECK (JSON_VALID(`category_ids`)),
    CONSTRAINT `json.blog.tag_ids` CHECK (JSON_VALID(`tag_ids`)),
    CONSTRAINT `fk.blog.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (!TableHelper::columnExists($connection, 'blog', 'type')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `blog`
    ADD COLUMN `type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post' AFTER `active`
SQL);
        }

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_translation` (
    `tenant_id`  BINARY(16)                              NULL,
    `blog_id`            BINARY(16)                              NOT NULL,
    `blog_version_id`    BINARY(16)                              NOT NULL,
    `language_id`        BINARY(16)                              NOT NULL,
    `meta_description`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `name`               VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `keywords`           LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `custom_search_keywords` JSON                                NULL,
    `description`        LONGTEXT COLLATE utf8mb4_unicode_ci    NULL,
    `description_teaser` VARCHAR(512) COLLATE utf8mb4_unicode_ci NULL,
    `meta_title`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `og_title`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `og_description`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields`      JSON                                    NULL,
    `created_at`         DATETIME(3)                             NOT NULL,
    `updated_at`         DATETIME(3)                             NULL,
    PRIMARY KEY (`blog_id`, `blog_version_id`, `language_id`),
    KEY `idx.blog_translation.tenant_id` (`tenant_id`),
    KEY `fk.blog_translation.language_id` (`language_id`),
    CONSTRAINT `fk.blog_translation.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.blog_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `json.blog_translation.custom_search_keywords` CHECK (JSON_VALID(`custom_search_keywords`)),
    CONSTRAINT `fk.blog_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_keyword` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`              BINARY(16)                              NOT NULL,
    `version_id`      BINARY(16)                              NOT NULL,
    `language_id`     BINARY(16)                              NOT NULL,
    `blog_id`         BINARY(16)                              NOT NULL,
    `blog_version_id` BINARY(16)                              NOT NULL,
    `keyword`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ranking`         DOUBLE                                  NOT NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`id`, `version_id`, `language_id`),
    KEY `idx.blog_search_keyword.tenant_id` (`tenant_id`),
    KEY `idx.blog_search_keyword.blog_id` (`blog_id`, `blog_version_id`),
    KEY `idx.blog_search_keyword.keyword_language` (`keyword`, `language_id`),
    KEY `idx.blog_search_keyword.language_id` (`language_id`),
    CONSTRAINT `fk.blog_search_keyword.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_search_keyword.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_search_keyword.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_keyword_dictionary` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`          BINARY(16)                              NOT NULL,
    `language_id` BINARY(16)                              NOT NULL,
    `keyword`     VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `reversed`    VARCHAR(500) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (REVERSE(`keyword`)) STORED,
    PRIMARY KEY (`id`, `language_id`),
    UNIQUE KEY `uniq.blog_keyword_dictionary.tenant_id_language_id_keyword` (`tenant_id`, `language_id`, `keyword`),
    KEY `idx.blog_keyword_dictionary.tenant_id` (`tenant_id`),
    KEY `idx.blog_keyword_dictionary.language_id` (`language_id`),
    CONSTRAINT `fk.blog_keyword_dictionary.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_keyword_dictionary.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_config` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`                BINARY(16)  NOT NULL,
    `language_id`       BINARY(16)  NOT NULL,
    `and_logic`         TINYINT(1)  NOT NULL DEFAULT 1,
    `min_search_length` SMALLINT    NOT NULL DEFAULT 2,
    `excluded_terms`    JSON        NULL,
    `created_at`        DATETIME(3) NOT NULL,
    `updated_at`        DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.blog_search_config.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.blog_search_config.tenant_id_language_id` (`tenant_id`, `language_id`),
    CONSTRAINT `json.blog_search_config.excluded_terms` CHECK (JSON_VALID(`excluded_terms`)),
    CONSTRAINT `fk.blog_search_config.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_search_config.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_config_field` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`                    BINARY(16)                              NOT NULL,
    `blog_search_config_id` BINARY(16)                              NOT NULL,
    `custom_field_id`       BINARY(16)                              NULL,
    `field`                 VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `tokenize`              TINYINT(1)                              NOT NULL DEFAULT 0,
    `searchable`            TINYINT(1)                              NOT NULL DEFAULT 0,
    `use_exact_subfield`    TINYINT(1)                              NOT NULL DEFAULT 0,
    `ranking`               INT                                    NOT NULL DEFAULT 0,
    `created_at`            DATETIME(3)                             NOT NULL,
    `updated_at`            DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.blog_search_config_field.tenant_id` (`tenant_id`),
    UNIQUE KEY `uniq.blog_search_config_field.field_config_id` (`field`, `blog_search_config_id`),
    KEY `fk.blog_search_config_field.custom_field_id` (`custom_field_id`),
    CONSTRAINT `fk.blog_search_config_field.search_config_id` FOREIGN KEY (`blog_search_config_id`)
        REFERENCES `blog_search_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_search_config_field.custom_field_id` FOREIGN KEY (`custom_field_id`)
        REFERENCES `custom_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_search_config_field.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_media` (
    `tenant_id`  BINARY(16)                              NULL,
    `id`              BINARY(16)  NOT NULL,
    `version_id`      BINARY(16)  NOT NULL,
    `position`        INT         NOT NULL DEFAULT 1,
    `blog_id`         BINARY(16)  NOT NULL,
    `blog_version_id` BINARY(16)  NOT NULL,
    `media_id`        BINARY(16)  NOT NULL,
    `custom_fields`   JSON        NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`id`, `version_id`),
    KEY `idx.blog_media.tenant_id` (`tenant_id`),
    KEY `fk.blog_media.media_id` (`media_id`),
    KEY `fk.blog_media.blog_id` (`blog_id`, `blog_version_id`),
    CONSTRAINT `fk.blog_media.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_media.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.blog_media.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.blog_media.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        foreach (['blog_category', 'blog_category_tree'] as $table) {
            $this->executeDdlStatement($connection, str_replace('#table#', $table, <<<'SQL'
CREATE TABLE IF NOT EXISTS `#table#` (
    `tenant_id`          BINARY(16) NULL,
    `blog_id`            BINARY(16) NOT NULL,
    `blog_version_id`    BINARY(16) NOT NULL,
    `category_id`        BINARY(16) NOT NULL,
    `category_version_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`blog_id`, `blog_version_id`, `category_id`, `category_version_id`),
    KEY `idx.#table#.tenant_id` (`tenant_id`),
    KEY `fk.#table#.category_id` (`category_id`, `category_version_id`),
    CONSTRAINT `fk.#table#.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.#table#.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
        REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.#table#.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL));
        }

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_tag` (
    `tenant_id`      BINARY(16) NULL,
    `blog_id`         BINARY(16) NOT NULL,
    `blog_version_id` BINARY(16) NOT NULL,
    `tag_id`          BINARY(16) NOT NULL,
    PRIMARY KEY (`blog_id`, `blog_version_id`, `tag_id`),
    KEY `idx.blog_tag.tenant_id` (`tenant_id`),
    KEY `fk.blog_tag.tag_id` (`tag_id`),
    CONSTRAINT `fk.blog_tag.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_tag.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
        REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createBlogSorting(Connection $connection): void
    {
        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_sorting` (
    `id`         BINARY(16)                              NOT NULL,
    `url_key`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `priority`   INT UNSIGNED                            NOT NULL,
    `active`     TINYINT(1)                              NOT NULL DEFAULT 1,
    `fields`     JSON                                    NOT NULL,
    `locked`     TINYINT(1)                              NOT NULL DEFAULT 0,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.blog_sorting.url_key` (`url_key`),
    CONSTRAINT `json.blog_sorting.fields` CHECK (JSON_VALID(`fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->executeDdlStatement($connection, <<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_sorting_translation` (
    `blog_sorting_id` BINARY(16)                              NOT NULL,
    `language_id`     BINARY(16)                              NOT NULL,
    `label`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`blog_sorting_id`, `language_id`),
    CONSTRAINT `fk.blog_sorting_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.blog_sorting_translation.blog_sorting_id` FOREIGN KEY (`blog_sorting_id`)
        REFERENCES `blog_sorting` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
