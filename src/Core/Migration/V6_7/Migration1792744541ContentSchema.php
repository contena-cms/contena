<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Categories, CMS, media, landing pages, mail templates.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744541ContentSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744541;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `category` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `media_id` binary(16) DEFAULT NULL,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `blog_stream_id` binary(16) DEFAULT NULL,
  `blog_assignment_type` varchar(32) NOT NULL DEFAULT 'blog',
  `path` longtext DEFAULT NULL,
  `after_category_id` binary(16) DEFAULT NULL,
  `after_category_version_id` binary(16) DEFAULT NULL,
  `level` int(11) unsigned NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `child_count` int(11) unsigned NOT NULL DEFAULT 0,
  `display_nested_products` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `visible` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `type` varchar(32) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `custom_entity_type_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  UNIQUE KEY `auto_increment` (`auto_increment`),
  KEY `idx.level` (`level`),
  KEY `fk.category.media_id` (`media_id`),
  KEY `fk.category.parent_id` (`parent_id`,`parent_version_id`),
  KEY `fk.category.after_category_id` (`after_category_id`,`after_category_version_id`),
  KEY `fk.category.blog_stream_id` (`blog_stream_id`),
  KEY `fk.category.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  KEY `fk.category.custom_entity_type_id` (`custom_entity_type_id`),
  CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.category.custom_entity_type_id` FOREIGN KEY (`custom_entity_type_id`) REFERENCES `custom_entity` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.category.blog_stream_id` FOREIGN KEY (`blog_stream_id`) REFERENCES `blog_stream` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `category_tag` (
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`category_id`,`category_version_id`,`tag_id`),
  KEY `fk.category_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.category_tag.category_tag__category_version_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.category_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `category_translation` (
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `breadcrumb` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`breadcrumb`)),
  `internal_link` binary(16) DEFAULT NULL,
  `link_new_tab` tinyint(4) DEFAULT NULL,
  `link_type` varchar(255) DEFAULT NULL,
  `external_link` mediumtext DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `slot_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slot_config`)),
  PRIMARY KEY (`category_id`,`category_version_id`,`language_id`),
  KEY `fk.category_translation.language_id` (`language_id`),
  CONSTRAINT `fk.category_translation.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.category_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.category_translation.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.category_translation.slot_config` CHECK (json_valid(`slot_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_block` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `cms_section_id` binary(16) DEFAULT NULL,
  `cms_section_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `position` int(11) NOT NULL,
  `section_position` varchar(50) DEFAULT 'main',
  `type` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `margin_top` varchar(255) DEFAULT NULL,
  `margin_bottom` varchar(255) DEFAULT NULL,
  `margin_left` varchar(255) DEFAULT NULL,
  `margin_right` varchar(255) DEFAULT NULL,
  `background_color` varchar(255) DEFAULT NULL,
  `background_media_id` binary(16) DEFAULT NULL,
  `background_media_mode` varchar(255) DEFAULT NULL,
  `visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visibility`)),
  `css_class` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.cms_block.background_media_id` (`background_media_id`),
  KEY `fk.cms_block.cms_section_id` (`cms_section_id`,`cms_section_version_id`),
  CONSTRAINT `fk.cms_block.background_media_id` FOREIGN KEY (`background_media_id`) REFERENCES `media` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.cms_block.cms_section_id` FOREIGN KEY (`cms_section_id`, `cms_section_version_id`) REFERENCES `cms_section` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.cms_block.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_page` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `type` varchar(255) NOT NULL,
  `entity` varchar(255) DEFAULT NULL,
  `preview_media_id` binary(16) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `css_class` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.cms_page.preview_media_id` (`preview_media_id`),
  CONSTRAINT `fk.cms_page.preview_media_id` FOREIGN KEY (`preview_media_id`) REFERENCES `media` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.cms_page.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_page_translation` (
  `cms_page_id` binary(16) NOT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`cms_page_id`,`language_id`,`cms_page_version_id`),
  KEY `fk.cms_page_translation.language_id` (`language_id`),
  KEY `fk.cms_page_translation.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  CONSTRAINT `fk.cms_page_translation.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.cms_page_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.cms_page_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_section` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `cms_page_id` binary(16) NOT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `position` int(11) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'default',
  `name` varchar(255) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `sizing_mode` varchar(255) NOT NULL DEFAULT 'boxed',
  `mobile_behavior` varchar(255) NOT NULL DEFAULT 'wrap',
  `background_color` varchar(255) DEFAULT NULL,
  `background_media_id` binary(16) DEFAULT NULL,
  `background_media_mode` varchar(255) DEFAULT NULL,
  `visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visibility`)),
  `css_class` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.cms_section.background_media_id` (`background_media_id`),
  KEY `fk.cms_section.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  CONSTRAINT `fk.cms_section.background_media_id` FOREIGN KEY (`background_media_id`) REFERENCES `media` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.cms_section.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.cms_section.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_slot` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `cms_block_id` binary(16) NOT NULL,
  `cms_block_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `type` varchar(255) NOT NULL,
  `slot` varchar(255) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.cms_slot.cms_block_id` (`cms_block_id`,`cms_block_version_id`),
  CONSTRAINT `fk.cms_slot.cms_block_id` FOREIGN KEY (`cms_block_id`, `cms_block_version_id`) REFERENCES `cms_block` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cms_slot_translation` (
  `cms_slot_id` binary(16) NOT NULL,
  `cms_slot_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`cms_slot_id`,`cms_slot_version_id`,`language_id`),
  KEY `fk.cms_slot_translation.language_id` (`language_id`),
  CONSTRAINT `fk.cms_slot_translation.cms_slot_id` FOREIGN KEY (`cms_slot_id`, `cms_slot_version_id`) REFERENCES `cms_slot` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.cms_slot_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.cms_slot_translation.config` CHECK (json_valid(`config`)),
  CONSTRAINT `json.cms_slot_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x'0fa91ce3e96a4bc2be4bd9ce752c3425',
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.landing_page.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  CONSTRAINT `fk.landing_page.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_channel` (
  `landing_page_id` binary(16) NOT NULL,
  `landing_page_version_id` binary(16) NOT NULL,
  `channel_id` binary(16) NOT NULL,
  PRIMARY KEY (`landing_page_id`,`landing_page_version_id`,`channel_id`),
  KEY `fk.landing_page_channel.channel_id` (`channel_id`),
  CONSTRAINT `fk.landing_page_channel.blog_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`) REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.landing_page_channel.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_tag` (
  `landing_page_id` binary(16) NOT NULL,
  `landing_page_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`landing_page_id`,`landing_page_version_id`,`tag_id`),
  KEY `fk.landing_page_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.landing_page_tag.landing_page_version_id__landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`) REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.landing_page_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `landing_page_translation` (
  `landing_page_id` binary(16) NOT NULL,
  `landing_page_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `slot_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slot_config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`landing_page_id`,`landing_page_version_id`,`language_id`),
  KEY `fk.landing_page_translation.language_id` (`language_id`),
  CONSTRAINT `fk.landing_page_translation.landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`) REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.landing_page_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.landing_page_translation.slot_config` CHECK (json_valid(`slot_config`)),
  CONSTRAINT `json.landing_page_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_header_footer` (
  `id` binary(16) NOT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_header_footer_translation` (
  `mail_header_footer_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `header_html` longtext DEFAULT NULL,
  `header_plain` longtext DEFAULT NULL,
  `footer_html` longtext DEFAULT NULL,
  `footer_plain` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`mail_header_footer_id`,`language_id`),
  KEY `fk.mail_header_footer_translation.language_id` (`language_id`),
  CONSTRAINT `fk.mail_header_footer_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_header_footer_translation.mail_header_footer_id` FOREIGN KEY (`mail_header_footer_id`) REFERENCES `mail_header_footer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template` (
  `id` binary(16) NOT NULL,
  `mail_template_type_id` binary(16) DEFAULT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `was_modified_by_user` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk.mail_template.mail_template_type_id` (`mail_template_type_id`),
  CONSTRAINT `fk.mail_template.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_media` (
  `id` binary(16) NOT NULL,
  `mail_template_id` binary(16) NOT NULL,
  `language_id` binary(16) DEFAULT NULL,
  `media_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk.mail_template_media.mail_template_id` (`mail_template_id`),
  KEY `fk.mail_template_media.media_id` (`media_id`),
  KEY `fk.mail_template_media.language_id` (`language_id`),
  CONSTRAINT `fk.mail_template_media.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_template_media.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_template_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_translation` (
  `mail_template_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `content_html` longtext DEFAULT NULL,
  `content_plain` longtext DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`mail_template_id`,`language_id`),
  KEY `fk.mail_template_translation.language_id` (`language_id`),
  CONSTRAINT `fk.mail_template_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_template_translation.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.mail_template_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_type` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `available_entities` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `template_data` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.mail_template_type.technical_name` (`technical_name`),
  CONSTRAINT `json.mail_template_type.available_entities` CHECK (json_valid(`available_entities`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_type_translation` (
  `mail_template_type_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`mail_template_type_id`,`language_id`),
  KEY `fk.mail_template_type_translation.language_id` (`language_id`),
  CONSTRAINT `fk.mail_template_type_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.mail_template_type_translation.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.mail_template_type_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `main_category` (
  `id` binary(16) NOT NULL,
  `blog_id` binary(16) NOT NULL,
  `blog_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `channel_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.main_category.channel_product` (`blog_id`,`blog_version_id`,`channel_id`),
  KEY `fk.main_category.channel_id` (`channel_id`),
  KEY `fk.main_category.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.main_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.main_category.blog_id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.main_category.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `media_folder_id` binary(16) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `meta_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_data`)),
  `file_name` longtext DEFAULT NULL,
  `media_type` longblob DEFAULT NULL,
  `thumbnails_ro` longblob DEFAULT NULL,
  `private` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `path` varchar(2048) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `file_hash` varchar(32) GENERATED ALWAYS AS (json_unquote(json_extract(`meta_data`,'$.hash'))) STORED,
  PRIMARY KEY (`id`),
  KEY `fk.media.user_id` (`user_id`),
  KEY `idx.media.file_extension` (`file_extension`),
  KEY `idx.media.file_name` (`file_name`(768)),
  KEY `idx.media.file_hash` (`file_hash`),
  KEY `idx.media.uploaded_at_created_at_id` (`uploaded_at`,`created_at`,`id`),
  KEY `idx.media.media_folder_id_created_at_id` (`media_folder_id`,`created_at`,`id`),
  CONSTRAINT `fk.media.media_folder_id` FOREIGN KEY (`media_folder_id`) REFERENCES `media_folder` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.media.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.media.meta_data` CHECK (json_valid(`meta_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_default_folder` (
  `id` binary(16) NOT NULL,
  `entity` varchar(255) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.media_default_folder.entity` (`entity`),
  CONSTRAINT `json.media_default_folder.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_folder` (
  `id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `default_folder_id` binary(16) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `child_count` int(11) unsigned NOT NULL DEFAULT 0,
  `path` longtext DEFAULT NULL,
  `media_folder_configuration_id` binary(16) DEFAULT NULL,
  `use_parent_configuration` tinyint(1) DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.media_folder.default_folder_id` (`default_folder_id`),
  KEY `fk.media_folder.parent_id` (`parent_id`),
  CONSTRAINT `fk.media_folder.default_folder_id` FOREIGN KEY (`default_folder_id`) REFERENCES `media_default_folder` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.media_folder.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `media_folder` (`id`) ON DELETE CASCADE,
  CONSTRAINT `json.media_folder.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_folder_configuration` (
  `id` binary(16) NOT NULL,
  `create_thumbnails` tinyint(1) DEFAULT 1,
  `thumbnail_quality` int(11) DEFAULT 80,
  `media_thumbnail_sizes_ro` longblob DEFAULT NULL,
  `keep_aspect_ratio` tinyint(1) DEFAULT 1,
  `private` tinyint(1) DEFAULT 0,
  `no_association` tinyint(1) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.media_folder_configuration.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_folder_configuration_media_thumbnail_size` (
  `media_folder_configuration_id` binary(16) NOT NULL,
  `media_thumbnail_size_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_folder_configuration_id`,`media_thumbnail_size_id`),
  KEY `fk.media_folder_configuration_media_thumbnail_size.size_id` (`media_thumbnail_size_id`),
  CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.conf_id` FOREIGN KEY (`media_folder_configuration_id`) REFERENCES `media_folder_configuration` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.size_id` FOREIGN KEY (`media_thumbnail_size_id`) REFERENCES `media_thumbnail_size` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_tag` (
  `media_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_id`,`tag_id`),
  KEY `fk.media_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.media_tag.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.media_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_thumbnail` (
  `id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `path` varchar(2048) DEFAULT NULL,
  `media_thumbnail_size_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.media_thumbnail.media_id` (`media_id`),
  KEY `fk.media_thumbnail.media_thumbnail_size_id` (`media_thumbnail_size_id`),
  CONSTRAINT `fk.media_thumbnail.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.media_thumbnail.media_thumbnail_size_id` FOREIGN KEY (`media_thumbnail_size_id`) REFERENCES `media_thumbnail_size` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.media_thumbnail.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_thumbnail_size` (
  `id` binary(16) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.width` (`width`,`height`),
  CONSTRAINT `json.media_thumbnail_size.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_translation` (
  `media_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `alt` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`media_id`,`language_id`),
  KEY `fk.media_translation.language_id` (`language_id`),
  CONSTRAINT `fk.media_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.media_translation.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.media_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
