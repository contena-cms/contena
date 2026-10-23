<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Blog entities, streams, properties, search configuration.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744542BlogSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744542;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` int(11) NOT NULL AUTO_INCREMENT,
  `blog_number` varchar(64) DEFAULT NULL,
  `active` tinyint(3) unsigned DEFAULT NULL,
  `blog_manufacturer_version_id` binary(16) DEFAULT NULL,
  `deliveryTime` binary(16) DEFAULT NULL,
  `blog_media_version_id` binary(16) DEFAULT NULL,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `unit_id` binary(16) DEFAULT NULL,
  `blog_feature_set_id` binary(16) DEFAULT NULL,
  `category_tree` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_tree`)),
  `category_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_ids`)),
  `stream_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stream_ids`)),
  `option_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`option_ids`)),
  `property_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`property_ids`)),
  `manufacturer` binary(16) DEFAULT NULL,
  `cover` binary(16) DEFAULT NULL,
  `unit` binary(16) DEFAULT NULL,
  `media` binary(16) DEFAULT NULL,
  `prices` binary(16) DEFAULT NULL,
  `visibilities` binary(16) DEFAULT NULL,
  `properties` binary(16) DEFAULT NULL,
  `categories` binary(16) DEFAULT NULL,
  `translations` binary(16) DEFAULT NULL,
  `sales` int(11) NOT NULL DEFAULT 0,
  `tag_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tag_ids`)),
  `tags` binary(16) DEFAULT NULL,
  `variant_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variant_restrictions`)),
  `created_at` datetime(3) DEFAULT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `display_group` varchar(64) DEFAULT NULL,
  `crossSellings` binary(16) DEFAULT NULL,
  `featureSet` binary(16) DEFAULT NULL,
  `customFieldSets` binary(16) DEFAULT NULL,
  `custom_field_set_selection_active` tinyint(1) DEFAULT NULL,
  `canonical_blog_id` binary(16) DEFAULT NULL,
  `canonical_blog_version_id` binary(16) DEFAULT NULL,
  `canonicalProduct` binary(16) DEFAULT NULL,
  `cmsPage` binary(16) DEFAULT NULL,
  `cheapest_price` longtext DEFAULT NULL,
  `cheapest_price_accessor` longtext DEFAULT NULL,
  `states` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`states`)),
  `type` varchar(32) NOT NULL DEFAULT 'physical',
  `mainCategories` binary(16) DEFAULT NULL,
  `open_graph_media_id` binary(16) DEFAULT NULL,
  `openGraphMedia` binary(16) DEFAULT NULL,
  `guarantee_confirmed` tinyint(1) DEFAULT NULL,
  `guarantee_months` int(11) DEFAULT NULL,
  `guarantee_terms_media_id` binary(16) DEFAULT NULL,
  `guarantee_terms_url` varchar(2048) DEFAULT NULL,
  `guaranteeTermsMedia` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  UNIQUE KEY `auto_increment` (`auto_increment`),
  UNIQUE KEY `uniq.blog.blog_number__version_id` (`blog_number`,`version_id`),
  KEY `fk.blog.unit_id` (`unit_id`),
  KEY `fk.blog.feature_set_id` (`blog_feature_set_id`),
  KEY `fk.blog.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  KEY `fk.blog.canonical_blog_id` (`canonical_blog_id`,`canonical_blog_version_id`),
  KEY `idx.blog.categories` (`categories`),
  KEY `idx.blog.type` (`type`),
  KEY `fk.blog.open_graph_media_id` (`open_graph_media_id`),
  KEY `fk.blog.guarantee_terms_media_id` (`guarantee_terms_media_id`),
  CONSTRAINT `fk.blog.canonical_blog_id` FOREIGN KEY (`canonical_blog_id`, `canonical_blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE SET NULL,
  CONSTRAINT `fk.blog.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.blog.guarantee_terms_media_id` FOREIGN KEY (`guarantee_terms_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.blog.open_graph_media_id` FOREIGN KEY (`open_graph_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.blog.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.blog.category_tree` CHECK (json_valid(`category_tree`)),
  CONSTRAINT `json.blog.option_ids` CHECK (json_valid(`option_ids`)),
  CONSTRAINT `json.blog.property_ids` CHECK (json_valid(`property_ids`)),
  CONSTRAINT `json.blog.tag_ids` CHECK (json_valid(`tag_ids`)),
  CONSTRAINT `json.blog.variant_restrictions` CHECK (json_valid(`variant_restrictions`)),
  CONSTRAINT `json.blog.states` CHECK (json_valid(`states`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_category` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`category_id`,`category_version_id`),
  KEY `fk.blog_category.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.blog_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_category.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_category_tree` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`category_id`,`category_version_id`),
  KEY `fk.blog_category_tree.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.blog_category_tree.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_category_tree.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_custom_field_set` (
  `custom_field_set_id` binary(16) NOT NULL,
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`custom_field_set_id`,`blog_id`,`blog_version_id`),
  KEY `fk.blog_custom_field_set.blog_id` (`blog_id`,`blog_version_id`),
  CONSTRAINT `fk.blog_custom_field_set.custom_field_set_id` FOREIGN KEY (`custom_field_set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.blog_custom_field_set.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_keyword_dictionary` (
  `id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `keyword` varchar(500) NOT NULL,
  `reversed` varchar(500) GENERATED ALWAYS AS (reverse(`keyword`)) STORED,
  PRIMARY KEY (`id`,`language_id`),
  UNIQUE KEY `uniq.language_id_keyword` (`language_id`,`keyword`),
  KEY `idx.blog_keyword_dictionary.language_id` (`language_id`),
  CONSTRAINT `fk.blog_keyword_dictionary.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_media` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.blog_media.media_id` (`media_id`),
  KEY `fk.blog_media.blog_id` (`blog_id`,`blog_version_id`),
  CONSTRAINT `fk.blog_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_media.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.blog_media.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_option` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `property_group_option_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`property_group_option_id`),
  KEY `fk.blog_option.property_group_option_id` (`property_group_option_id`),
  CONSTRAINT `fk.blog_option.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_option.property_group_option_id` FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_property` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `property_group_option_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`property_group_option_id`),
  KEY `fk.blog_property.configuration_option_id` (`property_group_option_id`),
  CONSTRAINT `fk.blog_property.configuration_option_id` FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_property.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_config` (
  `id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `and_logic` tinyint(1) NOT NULL DEFAULT 1,
  `min_search_length` smallint(6) NOT NULL DEFAULT 2,
  `excluded_terms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`excluded_terms`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.blog_search_config.language_id` (`language_id`),
  CONSTRAINT `fk.blog_search_config.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.blog_search_config.excluded_terms` CHECK (json_valid(`excluded_terms`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_config_field` (
  `id` binary(16) NOT NULL,
  `blog_search_config_id` binary(16) NOT NULL,
  `custom_field_id` binary(16) DEFAULT NULL,
  `field` varchar(255) NOT NULL,
  `tokenize` tinyint(1) NOT NULL DEFAULT 0,
  `searchable` tinyint(1) NOT NULL DEFAULT 0,
  `ranking` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `use_exact_subfield` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.search_config_field.field__config_id` (`field`,`blog_search_config_id`),
  KEY `fk.search_config_field.blog_search_config_id` (`blog_search_config_id`),
  KEY `fk.search_config_field.custom_field_id` (`custom_field_id`),
  CONSTRAINT `fk.search_config_field.custom_field_id` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.search_config_field.blog_search_config_id` FOREIGN KEY (`blog_search_config_id`) REFERENCES `blog_search_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_search_keyword` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `ranking` double NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`,`language_id`),
  KEY `idx.blog_search_keyword.blog_id` (`blog_id`,`blog_version_id`),
  KEY `idx.blog_search_keyword.language_id` (`language_id`),
  KEY `idx.blog_search_keyword.keyword_language` (`keyword`,`language_id`),
  CONSTRAINT `fk.blog_search_keyword.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_search_keyword.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_sorting` (
  `id` binary(16) NOT NULL,
  `url_key` varchar(255) NOT NULL,
  `priority` int(11) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`)),
  `created_at` datetime(3) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.blog_sorting.url_key` (`url_key`),
  CONSTRAINT `json.blog_sorting.fields` CHECK (json_valid(`fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_sorting_translation` (
  `blog_sorting_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`blog_sorting_id`,`language_id`),
  KEY `fk.blog_sorting_translation.language_id` (`language_id`),
  CONSTRAINT `fk.blog_sorting_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_sorting_translation.blog_sorting_id` FOREIGN KEY (`blog_sorting_id`) REFERENCES `blog_sorting` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_stream` (
  `id` binary(16) NOT NULL,
  `api_filter` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_filter`)),
  `invalid` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `internal` tinyint(1) NOT NULL DEFAULT 0,
  `display_as_group` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.blog_stream.api_filter` CHECK (json_valid(`api_filter`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_stream_filter` (
  `id` binary(16) NOT NULL,
  `blog_stream_id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `field` varchar(255) DEFAULT NULL,
  `operator` varchar(255) DEFAULT NULL,
  `value` longtext DEFAULT NULL,
  `parameters` longtext DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.blog_stream_filter.blog_stream_id` (`blog_stream_id`),
  KEY `fk.blog_stream_filter.parent_id` (`parent_id`),
  CONSTRAINT `fk.blog_stream_filter.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `blog_stream_filter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_stream_filter.blog_stream_id` FOREIGN KEY (`blog_stream_id`) REFERENCES `blog_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.blog_stream_filter.parameters` CHECK (json_valid(`parameters`)),
  CONSTRAINT `json.blog_stream_filter.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_stream_mapping` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `blog_stream_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`blog_stream_id`),
  KEY `fk.blog_stream_mapping.blog_stream_id` (`blog_stream_id`),
  CONSTRAINT `fk.blog_stream_mapping.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_stream_mapping.blog_stream_id` FOREIGN KEY (`blog_stream_id`) REFERENCES `blog_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_stream_translation` (
  `blog_stream_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`blog_stream_id`,`language_id`),
  KEY `fk.blog_stream_translation.language_id` (`language_id`),
  CONSTRAINT `fk.blog_stream_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_stream_translation.blog_stream_id` FOREIGN KEY (`blog_stream_id`) REFERENCES `blog_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.blog_stream_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_tag` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`tag_id`),
  KEY `fk.blog_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.blog_tag.blog_version_id__blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_translation` (
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `keywords` mediumtext DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `slot_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slot_config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `pack_unit_plural` varchar(255) DEFAULT NULL,
  `custom_search_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_search_keywords`)),
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` varchar(255) DEFAULT NULL,
  `description_teaser` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`blog_id`,`blog_version_id`,`language_id`),
  KEY `fk.blog_translation.language_id` (`language_id`),
  CONSTRAINT `fk.blog_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_translation.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.blog_translation.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.blog_translation.custom_search_keywords` CHECK (json_valid(`custom_search_keywords`)),
  CONSTRAINT `json.blog_translation.slot_config` CHECK (json_valid(`slot_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `blog_visibility` (
  `id` binary(16) NOT NULL,
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `channel_id` binary(16) NOT NULL,
  `visibility` int(11) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.blog_id__channel_id` (`blog_id`,`blog_version_id`,`channel_id`),
  KEY `idx.blog_visibility.blog_id` (`blog_id`,`blog_version_id`),
  KEY `idx.blog_visibility.channel_id` (`channel_id`),
  CONSTRAINT `fk.blog_visibility.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.blog_visibility.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `property_group` (
  `id` binary(16) NOT NULL,
  `sorting_type` varchar(50) NOT NULL DEFAULT 'alphanumeric',
  `display_type` varchar(50) NOT NULL DEFAULT 'text',
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `filterable` tinyint(1) NOT NULL DEFAULT 1,
  `visible_on_blog_detail_page` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `property_group_option` (
  `id` binary(16) NOT NULL,
  `property_group_id` binary(16) NOT NULL,
  `color_hex_code` varchar(20) DEFAULT NULL,
  `media_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.property_group_option.property_group_id` (`property_group_id`),
  KEY `fk.property_group_option.media_id` (`media_id`),
  CONSTRAINT `fk.property_group_option.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.property_group_option.property_group_id` FOREIGN KEY (`property_group_id`) REFERENCES `property_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `property_group_option_translation` (
  `property_group_option_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`property_group_option_id`,`language_id`),
  KEY `fk.property_group_option_translation.language_id` (`language_id`),
  CONSTRAINT `fk.property_group_option_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.property_group_option_translation.prop_group_option_id` FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.property_group_option_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `property_group_translation` (
  `property_group_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `position` int(11) DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`property_group_id`,`language_id`),
  KEY `fk.property_group_translation.language_id` (`language_id`),
  CONSTRAINT `fk.property_group_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.property_group_translation.property_group_id` FOREIGN KEY (`property_group_id`) REFERENCES `property_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.property_group_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tag` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
