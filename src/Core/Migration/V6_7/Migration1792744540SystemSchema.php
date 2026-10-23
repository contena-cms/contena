<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Languages, currencies, countries, channels, snippets, config.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744540SystemSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744540;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel` (
  `id` binary(16) NOT NULL,
  `type_id` binary(16) NOT NULL,
  `short_name` varchar(45) DEFAULT NULL,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `access_key` varchar(255) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `navigation_category_id` binary(16) NOT NULL,
  `navigation_category_version_id` binary(16) NOT NULL,
  `navigation_category_depth` int(11) NOT NULL DEFAULT 2,
  `hreflang_active` tinyint(1) unsigned DEFAULT 0,
  `hreflang_default_domain_id` binary(16) DEFAULT NULL,
  `footer_category_id` binary(16) DEFAULT NULL,
  `footer_category_version_id` binary(16) DEFAULT NULL,
  `service_category_id` binary(16) DEFAULT NULL,
  `service_category_version_id` binary(16) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `maintenance` tinyint(1) NOT NULL DEFAULT 0,
  `maintenance_ip_whitelist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maintenance_ip_whitelist`)),
  `customer_group_id` binary(16) NOT NULL,
  `mail_header_footer_id` binary(16) DEFAULT NULL,
  `payment_method_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_method_ids`)),
  `analytics_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `home_cms_page_id` binary(16) DEFAULT NULL,
  `home_cms_page_version_id` binary(16) DEFAULT NULL,
  `measurement_units` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`measurement_units`)),
  `maintenance_ip_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maintenance_ip_allowlist`)),
  `business_time_zone` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.access_key` (`access_key`),
  KEY `fk.channel.country_id` (`country_id`),
  KEY `fk.channel.currency_id` (`currency_id`),
  KEY `fk.channel.language_id` (`language_id`),
  KEY `fk.channel.payment_method_id` (`payment_method_id`),
  KEY `fk.channel.shipping_method_id` (`shipping_method_id`),
  KEY `fk.channel.type_id` (`type_id`),
  KEY `fk.channel.navigation_category_id` (`navigation_category_id`,`navigation_category_version_id`),
  KEY `fk.channel.footer_category_id` (`footer_category_id`,`footer_category_version_id`),
  KEY `fk.channel.service_category_id` (`service_category_id`,`service_category_version_id`),
  KEY `fk.channel.customer_group_id` (`customer_group_id`),
  KEY `fk.channel.hreflang_default_domain_id` (`hreflang_default_domain_id`),
  KEY `fk.channel.header_footer_id` (`mail_header_footer_id`),
  KEY `fk.channel.home_cms_page` (`home_cms_page_id`,`home_cms_page_version_id`),
  KEY `fk.channel.analytics_id` (`analytics_id`),
  CONSTRAINT `fk.channel.analytics_id` FOREIGN KEY (`analytics_id`) REFERENCES `sales_channel_analytics` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.footer_category_id` FOREIGN KEY (`footer_category_id`, `footer_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.header_footer_id` FOREIGN KEY (`mail_header_footer_id`) REFERENCES `mail_header_footer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.home_cms_page` FOREIGN KEY (`home_cms_page_id`, `home_cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.hreflang_default_domain_id` FOREIGN KEY (`hreflang_default_domain_id`) REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.navigation_category_id` FOREIGN KEY (`navigation_category_id`, `navigation_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.service_category_id` FOREIGN KEY (`service_category_id`, `service_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.channel.type_id` FOREIGN KEY (`type_id`) REFERENCES `sales_channel_type` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.channel.configuration` CHECK (json_valid(`configuration`)),
  CONSTRAINT `json.channel.payment_method_ids` CHECK (json_valid(`payment_method_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_analytics` (
  `id` binary(16) NOT NULL,
  `tracking_id` varchar(50) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `track_orders` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `anonymize_ip` tinyint(1) NOT NULL DEFAULT 1,
  `track_offcanvas_cart` tinyint(1) NOT NULL DEFAULT 0,
  `enhanced_conversions` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_api_context` (
  `token` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `sales_channel_id` binary(16) DEFAULT NULL,
  `customer_id` binary(16) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`token`),
  UNIQUE KEY `uniq.sales_channel_api_context.sales_channel_id_customer_id` (`sales_channel_id`,`customer_id`),
  KEY `fk.sales_channel_api_context.customer_id` (`customer_id`),
  CONSTRAINT `fk.sales_channel_api_context.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.sales_channel_api_context.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_country` (
  `sales_channel_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`country_id`),
  KEY `fk.sales_channel_country.country_id` (`country_id`),
  CONSTRAINT `fk.sales_channel_country.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_country.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_currency` (
  `sales_channel_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`currency_id`),
  KEY `fk.sales_channel_currency.currency_id` (`currency_id`),
  CONSTRAINT `fk.sales_channel_currency.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_currency.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_domain` (
  `id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `url` varchar(255) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `snippet_set_id` binary(16) NOT NULL,
  `hreflang_use_only_locale` tinyint(1) unsigned DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `measurement_units` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`measurement_units`)),
  `is_external_storefront` tinyint(1) NOT NULL DEFAULT 0,
  `external_storefront_language_id` binary(16) GENERATED ALWAYS AS (if(`is_external_storefront` = 1,`language_id`,NULL)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.sales_channel_domain.url` (`url`),
  UNIQUE KEY `uniq.sales_channel_domain.external_storefront` (`external_storefront_language_id`,`sales_channel_id`),
  KEY `fk.sales_channel_domain.currency_id` (`currency_id`),
  KEY `fk.sales_channel_domain.snippet_set_id` (`snippet_set_id`),
  KEY `fk.sales_channel_domain.language_id` (`language_id`),
  KEY `fk.sales_channel_domain.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.sales_channel_domain.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_domain.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_domain.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_domain.snippet_set_id` FOREIGN KEY (`snippet_set_id`) REFERENCES `snippet_set` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_domain.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_file` (
  `id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `file_family` varchar(64) NOT NULL,
  `file_name` varchar(512) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `template_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_overrides`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.sales_channel_file.sc_id_family_file_name` (`sales_channel_id`,`file_family`,`file_name`),
  CONSTRAINT `fk.sales_channel_file.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_file.template_overrides` CHECK (json_valid(`template_overrides`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_language` (
  `sales_channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`language_id`),
  KEY `fk.sales_channel_language.language_id` (`language_id`),
  CONSTRAINT `fk.sales_channel_language.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_language.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_payment_method` (
  `sales_channel_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`payment_method_id`),
  KEY `fk.sales_channel_payment_method.payment_method_id` (`payment_method_id`),
  CONSTRAINT `fk.sales_channel_payment_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_translation` (
  `sales_channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `home_keywords` varchar(255) DEFAULT NULL,
  `home_meta_description` varchar(255) DEFAULT NULL,
  `home_meta_title` varchar(255) DEFAULT NULL,
  `home_name` varchar(255) DEFAULT NULL,
  `home_enabled` tinyint(4) NOT NULL DEFAULT 1,
  `home_slot_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`home_slot_config`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`sales_channel_id`,`language_id`),
  KEY `fk.sales_channel_translation.language_id` (`language_id`),
  CONSTRAINT `fk.sales_channel_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_translation.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_translation.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.sales_channel_translation.home_slot_config` CHECK (json_valid(`home_slot_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_type` (
  `id` binary(16) NOT NULL,
  `cover_url` varchar(500) DEFAULT NULL,
  `icon_name` varchar(255) DEFAULT NULL,
  `screenshot_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`screenshot_urls`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.sales_channel_type.screenshot_urls` CHECK (json_valid(`screenshot_urls`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_type_translation` (
  `sales_channel_type_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `description_long` longtext DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`sales_channel_type_id`,`language_id`),
  KEY `fk.sales_channel_type_translation.language_id` (`language_id`),
  CONSTRAINT `fk.sales_channel_type_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_type_translation.sales_channel_type_id` FOREIGN KEY (`sales_channel_type_id`) REFERENCES `sales_channel_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_type_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `country` (
  `id` binary(16) NOT NULL,
  `iso` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `iso3` varchar(45) DEFAULT NULL,
  `display_state_in_registration` tinyint(1) NOT NULL DEFAULT 0,
  `force_state_in_registration` tinyint(1) NOT NULL DEFAULT 0,
  `check_vat_id_pattern` tinyint(1) NOT NULL DEFAULT 0,
  `vat_id_pattern` varchar(255) DEFAULT NULL,
  `vat_id_required` tinyint(1) NOT NULL DEFAULT 0,
  `advanced_postal_code_pattern` varchar(1024) DEFAULT NULL,
  `check_advanced_postal_code_pattern` tinyint(1) NOT NULL DEFAULT 0,
  `check_postal_code_pattern` tinyint(1) NOT NULL DEFAULT 0,
  `default_postal_code_pattern` varchar(1024) DEFAULT NULL,
  `postal_code_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `shipping_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_eu` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `country_state` (
  `id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `short_code` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.country_state.country_id` (`country_id`),
  CONSTRAINT `fk.country_state.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `country_state_translation` (
  `country_state_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`country_state_id`,`language_id`),
  KEY `fk.country_state_translation.language_id` (`language_id`),
  CONSTRAINT `fk.country_state_translation.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.country_state_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.country_state_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `country_translation` (
  `country_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address_format` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address_format`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`country_id`,`language_id`),
  KEY `fk.country_translation.language_id` (`language_id`),
  CONSTRAINT `fk.country_translation.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.country_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.country_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `currency` (
  `id` binary(16) NOT NULL,
  `iso_code` char(3) NOT NULL,
  `factor` double NOT NULL,
  `symbol` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `item_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`item_rounding`)),
  `total_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`total_rounding`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.currency.iso_code` (`iso_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `currency_country_rounding` (
  `id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `item_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`item_rounding`)),
  `total_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`total_rounding`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_id` (`currency_id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `currency_country_rounding_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `currency_country_rounding_ibfk_3` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `currency_translation` (
  `currency_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `short_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`currency_id`,`language_id`),
  KEY `fk.currency_translation.language_id` (`language_id`),
  CONSTRAINT `fk.currency_translation.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.currency_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.currency_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `language` (
  `id` binary(16) NOT NULL,
  `name` varchar(50) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `locale_id` binary(16) NOT NULL,
  `translation_code_id` binary(16) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `translation_auto_update` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx.language.translation_code_id` (`translation_code_id`),
  KEY `idx.language.language_id_parent_language_id` (`id`,`parent_id`),
  KEY `fk.language.parent_id` (`parent_id`),
  KEY `fk.language.locale_id` (`locale_id`),
  CONSTRAINT `fk.language.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.language.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.language.translation_code_id` FOREIGN KEY (`translation_code_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.language.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `locale` (
  `id` binary(16) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `locale_translation` (
  `locale_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `territory` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`locale_id`,`language_id`),
  KEY `fk.locale_translation.language_id` (`language_id`),
  CONSTRAINT `fk.locale_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.locale_translation.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.locale_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `snippet` (
  `id` binary(16) NOT NULL,
  `translation_key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  `author` varchar(255) NOT NULL,
  `snippet_set_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.snippet_set_id_translation_key` (`snippet_set_id`,`translation_key`),
  CONSTRAINT `fk.snippet.snippet_set_id` FOREIGN KEY (`snippet_set_id`) REFERENCES `snippet_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.snippet.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `snippet_set` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `base_file` varchar(255) NOT NULL,
  `iso` varchar(255) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.snippet_set.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` binary(16) NOT NULL,
  `configuration_key` varchar(255) NOT NULL,
  `configuration_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuration_value`)),
  `sales_channel_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.system_config.configuration_key__sales_channel_id` (`configuration_key`,`sales_channel_id`),
  KEY `fk.system_config.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.system_config.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.system_config.configuration_value` CHECK (json_valid(`configuration_value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
