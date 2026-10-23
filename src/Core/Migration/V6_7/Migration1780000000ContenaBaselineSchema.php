<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;

/**
 * Contena baseline schema (merged). Generated from the upstream final schema;
 * do not edit manually — regenerate via scripts/generate-baseline-migration.py.
 */
class Migration1780000000ContenaBaselineSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `acl_role` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`privileges`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `deleted_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `acl_user_role` (
            `user_id` binary(16
        ) NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`acl_role_id`),
  KEY `fk.acl_user_role.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.acl_user_role.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.acl_user_role.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `admin_elasticsearch_index_task` (
            `id` binary(16
        ) NOT NULL,
  `index` varchar(500) NOT NULL,
  `alias` varchar(500) NOT NULL,
  `entity` varchar(500) NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `admin_user` (
            `id` binary(16
        ) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `admin` tinyint(1) DEFAULT NULL,
  `avatar_id` binary(16) DEFAULT NULL,
  `locale_id` binary(16) NOT NULL,
  `store_token` varchar(255) DEFAULT NULL,
  `last_updated_password_at` datetime(3) DEFAULT NULL,
  `time_zone` varchar(255) NOT NULL DEFAULT \'UTC\',
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `mcp_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mcp_allowlist`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.user.email` (`email`),
  UNIQUE KEY `uniq.user.username` (`username`),
  KEY `fk.user.locale_id` (`locale_id`),
  KEY `fk.user.avatar_id` (`avatar_id`),
  CONSTRAINT `fk.user.avatar_id` FOREIGN KEY (`avatar_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.user.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.user.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `admin_user_access_key` (
            `id` binary(16
        ) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `secret_access_key` varchar(255) NOT NULL,
  `last_usage_at` datetime(3) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.user_access_key.user_id_` (`user_id`),
  KEY `idx.user_access_key.access_key` (`access_key`),
  CONSTRAINT `fk.user_access_key.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.user_access_key.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `admin_user_config` (
            `id` binary(16
        ) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.user_id_key` (`user_id`,`key`),
  CONSTRAINT `fk.user_config.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.user_config.value` CHECK (json_valid(`value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `admin_user_recovery` (
            `id` binary(16
        ) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.user_recovery.user_id` (`user_id`),
  CONSTRAINT `fk.user_recovery.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `path` varchar(4096) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `copyright` varchar(255) DEFAULT NULL,
  `license` varchar(255) DEFAULT NULL,
  `privacy` varchar(255) DEFAULT NULL,
  `version` varchar(255) NOT NULL,
  `base_app_url` varchar(1024) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `allow_disable` tinyint(1) NOT NULL DEFAULT 1,
  `configurable` tinyint(1) NOT NULL DEFAULT 0,
  `icon` mediumblob DEFAULT NULL,
  `app_secret` varchar(255) DEFAULT NULL,
  `modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modules`)),
  `main_module` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`main_module`)),
  `cookies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cookies`)),
  `allowed_hosts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_hosts`)),
  `integration_id` binary(16) NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `template_load_priority` int(11) DEFAULT 0,
  `checkout_gateway_url` varchar(255) DEFAULT NULL,
  `self_managed` tinyint(1) NOT NULL DEFAULT 0,
  `source_type` varchar(20) NOT NULL DEFAULT \'local\',
  `source_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_object() CHECK (json_valid(`source_config`)),
  `in_app_purchases_gateway_url` varchar(255) DEFAULT NULL,
  `requested_privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_array() CHECK (json_valid(`requested_privileges`)),
  `context_gateway_url` varchar(255) DEFAULT NULL,
  `unconfirmed_app_secrets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unconfirmed_app_secrets`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.name` (`name`),
  KEY `fk.app.integration_id` (`integration_id`),
  KEY `fk.app.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.app.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.app.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.app.modules` CHECK (json_valid(`modules`)),
  CONSTRAINT `json.app.cookies` CHECK (json_valid(`cookies`)),
  CONSTRAINT `json.app.main_module` CHECK (json_valid(`main_module`)),
  CONSTRAINT `json.app.allowed_hosts` CHECK (json_valid(`allowed_hosts`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_action_button` (
            `id` binary(16
        ) NOT NULL,
  `entity` varchar(255) NOT NULL,
  `view` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.app_action_button.action` (`action`,`app_id`),
  KEY `fk.app_action_button.app_id` (`app_id`),
  CONSTRAINT `fk.app_action_button.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_action_button_translation` (
            `label` varchar(255
        ) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `app_action_button_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`app_action_button_id`,`language_id`),
  KEY `fk.app_action_button_translation.app_action_button_id` (`app_action_button_id`),
  KEY `fk.app_action_button_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_action_button_translation.app_action_button_id` FOREIGN KEY (`app_action_button_id`) REFERENCES `app_action_button` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_action_button_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_administration_snippet` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.app_id` (`app_id`),
  KEY `fk.locale_id` (`locale_id`),
  CONSTRAINT `fk.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_cms_block` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `block` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`block`)),
  `template` longtext NOT NULL,
  `styles` longtext NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.app_cms_block.app_id` (`app_id`),
  CONSTRAINT `fk.app_cms_block.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.app_cms_block.block` CHECK (json_valid(`block`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_cms_block_translation` (
            `label` varchar(255
        ) DEFAULT NULL,
  `app_cms_block_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_cms_block_id`,`language_id`),
  KEY `fk.app_cms_block_translation.app_cms_block_id` (`app_cms_block_id`),
  KEY `fk.app_cms_block_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_cms_block.app_cms_block_id` FOREIGN KEY (`app_cms_block_id`) REFERENCES `app_cms_block` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_cms_block.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_config` (
            `key` varchar(255
        ) NOT NULL,
  `value` longtext NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_feature` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) DEFAULT NULL,
  `app_name` varchar(255) NOT NULL,
  `type` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.app_feature.app_name_type_name` (`app_name`,`type`,`name`),
  KEY `idx.app_feature.type` (`type`),
  KEY `fk.app_feature.app_id` (`app_id`),
  CONSTRAINT `fk.app_feature.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_flow_action` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `badge` varchar(255) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `delayable` tinyint(1) NOT NULL DEFAULT 0,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `icon` mediumblob DEFAULT NULL,
  `sw_icon` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.app_flow_action.name` (`name`),
  KEY `fk.app_flow_action.app_id` (`app_id`),
  CONSTRAINT `fk.app_flow_action.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.app_flow_action.parameters` CHECK (json_valid(`parameters`)),
  CONSTRAINT `json.app_flow_action.config` CHECK (json_valid(`config`)),
  CONSTRAINT `json.app_flow_action.headers` CHECK (json_valid(`headers`)),
  CONSTRAINT `json.app_flow_action.requirements` CHECK (json_valid(`requirements`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_flow_action_translation` (
            `app_flow_action_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_flow_action_id`,`language_id`),
  KEY `fk.app_flow_action_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_flow_action_translation.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_flow_action_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.app_flow_action_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_flow_event` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `aware` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`aware`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uniq.app_flow_event.name` (`name`),
  KEY `fk.app_flow_event.app_id` (`app_id`),
  CONSTRAINT `fk.app_flow_event.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.app_flow_event.aware` CHECK (json_valid(`aware`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_script_condition` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `group` varchar(255) DEFAULT NULL,
  `script` longtext DEFAULT NULL,
  `constraints` longblob DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.app_script_condition.app_id` (`app_id`),
  CONSTRAINT `fk.app_script_condition.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_script_condition_translation` (
            `app_script_condition_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_script_condition_id`,`language_id`),
  KEY `fk.app_script_condition_translation.app_script_condition_id` (`app_script_condition_id`),
  KEY `fk.app_script_condition_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_script_condition_translation.app_script_condition_id` FOREIGN KEY (`app_script_condition_id`) REFERENCES `app_script_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_script_condition_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_shipping_method` (
            `id` binary(16
        ) NOT NULL,
  `app_id` binary(16) DEFAULT NULL,
  `app_name` varchar(255) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `original_media_id` binary(16) DEFAULT NULL,
  `identifier` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.app_shipping_method.shipping_method_id` (`shipping_method_id`),
  KEY `fk.app_shipping_method.app_id` (`app_id`),
  KEY `fk.app_shipping_method.original_media_id` (`original_media_id`),
  CONSTRAINT `fk.app_shipping_method.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.app_shipping_method.original_media_id` FOREIGN KEY (`original_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.app_shipping_method.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_template` (
            `id` binary(16
        ) NOT NULL,
  `template` longtext NOT NULL,
  `path` varchar(1024) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `app_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `hash` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.template.path` (`path`(256)),
  KEY `fk.template.app_id` (`app_id`),
  CONSTRAINT `fk.template.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `app_translation` (
            `app_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `privacy_policy_extensions` mediumtext DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_id`,`language_id`),
  KEY `fk.app_translation.app_id` (`app_id`),
  KEY `fk.app_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_translation.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` int(11) NOT NULL AUTO_INCREMENT,
  `product_number` varchar(64) DEFAULT NULL,
  `active` tinyint(3) unsigned DEFAULT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `tax_id` binary(16) DEFAULT NULL,
  `product_manufacturer_id` binary(16) DEFAULT NULL,
  `product_manufacturer_version_id` binary(16) DEFAULT NULL,
  `delivery_time_id` binary(16) DEFAULT NULL,
  `deliveryTime` binary(16) DEFAULT NULL,
  `product_media_id` binary(16) DEFAULT NULL,
  `product_media_version_id` binary(16) DEFAULT NULL,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `unit_id` binary(16) DEFAULT NULL,
  `product_feature_set_id` binary(16) DEFAULT NULL,
  `category_tree` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_tree`)),
  `category_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_ids`)),
  `stream_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stream_ids`)),
  `option_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`option_ids`)),
  `property_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`property_ids`)),
  `tax` binary(16) DEFAULT NULL,
  `manufacturer` binary(16) DEFAULT NULL,
  `cover` binary(16) DEFAULT NULL,
  `unit` binary(16) DEFAULT NULL,
  `media` binary(16) DEFAULT NULL,
  `prices` binary(16) DEFAULT NULL,
  `visibilities` binary(16) DEFAULT NULL,
  `properties` binary(16) DEFAULT NULL,
  `categories` binary(16) DEFAULT NULL,
  `translations` binary(16) DEFAULT NULL,
  `price` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`price`)),
  `manufacturer_number` varchar(255) DEFAULT NULL,
  `ean` varchar(255) DEFAULT NULL,
  `sales` int(11) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL,
  `available_stock` int(11) DEFAULT NULL,
  `available` tinyint(1) NOT NULL DEFAULT 0,
  `restock_time` int(11) DEFAULT NULL,
  `is_closeout` tinyint(1) DEFAULT NULL,
  `purchase_steps` int(11) unsigned DEFAULT NULL,
  `max_purchase` int(11) unsigned DEFAULT NULL,
  `min_purchase` int(11) unsigned DEFAULT NULL,
  `purchase_unit` decimal(11,4) unsigned DEFAULT NULL,
  `reference_unit` decimal(10,3) unsigned DEFAULT NULL,
  `shipping_free` tinyint(1) DEFAULT NULL,
  `purchase_prices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`purchase_prices`)),
  `mark_as_topseller` tinyint(1) unsigned DEFAULT NULL,
  `weight` decimal(15,6) unsigned DEFAULT NULL,
  `width` decimal(10,3) unsigned DEFAULT NULL,
  `height` decimal(10,3) unsigned DEFAULT NULL,
  `length` decimal(10,3) unsigned DEFAULT NULL,
  `release_date` datetime(3) DEFAULT NULL,
  `tag_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tag_ids`)),
  `tags` binary(16) DEFAULT NULL,
  `variant_restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variant_restrictions`)),
  `created_at` datetime(3) DEFAULT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `rating_average` float DEFAULT NULL,
  `display_group` varchar(64) DEFAULT NULL,
  `child_count` int(11) DEFAULT NULL,
  `crossSellings` binary(16) DEFAULT NULL,
  `featureSet` binary(16) DEFAULT NULL,
  `customFieldSets` binary(16) DEFAULT NULL,
  `custom_field_set_selection_active` tinyint(1) DEFAULT NULL,
  `canonical_product_id` binary(16) DEFAULT NULL,
  `canonical_product_version_id` binary(16) DEFAULT NULL,
  `canonicalBlog` binary(16) DEFAULT NULL,
  `cmsPage` binary(16) DEFAULT NULL,
  `cheapest_price` longtext DEFAULT NULL,
  `cheapest_price_accessor` longtext DEFAULT NULL,
  `states` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`states`)),
  `variant_listing_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variant_listing_config`)),
  `type` varchar(32) NOT NULL DEFAULT \'physical\',
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
  UNIQUE KEY `uniq.product.product_number__version_id` (`product_number`,`version_id`),
  KEY `fk.product.tax_id` (`tax_id`),
  KEY `fk.product.unit_id` (`unit_id`),
  KEY `fk.product.parent_id` (`parent_id`,`parent_version_id`),
  KEY `fk.product.product_media_id` (`product_media_id`,`product_media_version_id`),
  KEY `fk.product.feature_set_id` (`product_feature_set_id`),
  KEY `fk.product.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  KEY `fk.product.product_manufacturer` (`product_manufacturer_id`,`product_manufacturer_version_id`),
  KEY `fk.product.canonical_product_id` (`canonical_product_id`,`canonical_product_version_id`),
  KEY `idx.product.categories` (`categories`),
  KEY `idx.product.type` (`type`),
  KEY `fk.product.open_graph_media_id` (`open_graph_media_id`),
  KEY `fk.product.guarantee_terms_media_id` (`guarantee_terms_media_id`),
  CONSTRAINT `fk.product.canonical_product_id` FOREIGN KEY (`canonical_product_id`, `canonical_product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL,
  CONSTRAINT `fk.product.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.product.feature_set_id` FOREIGN KEY (`product_feature_set_id`) REFERENCES `product_feature_set` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.product.guarantee_terms_media_id` FOREIGN KEY (`guarantee_terms_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.product.open_graph_media_id` FOREIGN KEY (`open_graph_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.product.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product.product_manufacturer` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`) REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.product.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.product.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.product.category_tree` CHECK (json_valid(`category_tree`)),
  CONSTRAINT `json.product.option_ids` CHECK (json_valid(`option_ids`)),
  CONSTRAINT `json.product.property_ids` CHECK (json_valid(`property_ids`)),
  CONSTRAINT `json.product.price` CHECK (json_valid(`price`)),
  CONSTRAINT `json.product.tag_ids` CHECK (json_valid(`tag_ids`)),
  CONSTRAINT `json.product.variant_restrictions` CHECK (json_valid(`variant_restrictions`)),
  CONSTRAINT `json.product.states` CHECK (json_valid(`states`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_category` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`category_id`,`category_version_id`),
  KEY `fk.product_category.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.product_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_category.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_media` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.product_media.media_id` (`media_id`),
  KEY `fk.product_media.product_id` (`product_id`,`product_version_id`),
  CONSTRAINT `fk.product_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_media.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.product_media.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_search_keyword` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `ranking` double NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`,`language_id`),
  KEY `idx.product_search_keyword.product_id` (`product_id`,`product_version_id`),
  KEY `idx.product_search_keyword.language_id` (`language_id`),
  KEY `idx.product_search_keyword.keyword_language` (`keyword`,`language_id`),
  CONSTRAINT `fk.product_search_keyword.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_search_keyword.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_sorting` (
            `id` binary(16
        ) NOT NULL,
  `url_key` varchar(255) NOT NULL,
  `priority` int(11) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`)),
  `created_at` datetime(3) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.product_sorting.url_key` (`url_key`),
  CONSTRAINT `json.product_sorting.fields` CHECK (json_valid(`fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_tag` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`tag_id`),
  KEY `fk.product_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.product_tag.product_version_id__product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `blog_translation` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `keywords` mediumtext DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `pack_unit` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `slot_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slot_config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `pack_unit_plural` varchar(255) DEFAULT NULL,
  `custom_search_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_search_keywords`)),
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` varchar(255) DEFAULT NULL,
  `description_teaser` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`language_id`),
  KEY `fk.product_translation.language_id` (`language_id`),
  CONSTRAINT `fk.product_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_translation.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.product_translation.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.product_translation.custom_search_keywords` CHECK (json_valid(`custom_search_keywords`)),
  CONSTRAINT `json.product_translation.slot_config` CHECK (json_valid(`slot_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `category` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `media_id` binary(16) DEFAULT NULL,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `product_stream_id` binary(16) DEFAULT NULL,
  `product_assignment_type` varchar(32) NOT NULL DEFAULT \'product\',
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
  KEY `fk.category.product_stream_id` (`product_stream_id`),
  KEY `fk.category.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  KEY `fk.category.custom_entity_type_id` (`custom_entity_type_id`),
  CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.category.custom_entity_type_id` FOREIGN KEY (`custom_entity_type_id`) REFERENCES `custom_entity` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.category.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `category_tag` (
            `category_id` binary(16
        ) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`category_id`,`category_version_id`,`tag_id`),
  KEY `fk.category_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.category_tag.category_tag__category_version_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.category_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `category_translation` (
            `category_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel` (
            `id` binary(16
        ) NOT NULL,
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
  `tax_calculation_type` varchar(50) NOT NULL DEFAULT \'horizontal\',
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `home_cms_page_id` binary(16) DEFAULT NULL,
  `home_cms_page_version_id` binary(16) DEFAULT NULL,
  `measurement_units` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`measurement_units`)),
  `maintenance_ip_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maintenance_ip_allowlist`)),
  `business_time_zone` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.access_key` (`access_key`),
  KEY `fk.sales_channel.country_id` (`country_id`),
  KEY `fk.sales_channel.currency_id` (`currency_id`),
  KEY `fk.sales_channel.language_id` (`language_id`),
  KEY `fk.sales_channel.payment_method_id` (`payment_method_id`),
  KEY `fk.sales_channel.shipping_method_id` (`shipping_method_id`),
  KEY `fk.sales_channel.type_id` (`type_id`),
  KEY `fk.sales_channel.navigation_category_id` (`navigation_category_id`,`navigation_category_version_id`),
  KEY `fk.sales_channel.footer_category_id` (`footer_category_id`,`footer_category_version_id`),
  KEY `fk.sales_channel.service_category_id` (`service_category_id`,`service_category_version_id`),
  KEY `fk.sales_channel.customer_group_id` (`customer_group_id`),
  KEY `fk.sales_channel.hreflang_default_domain_id` (`hreflang_default_domain_id`),
  KEY `fk.sales_channel.header_footer_id` (`mail_header_footer_id`),
  KEY `fk.sales_channel.home_cms_page` (`home_cms_page_id`,`home_cms_page_version_id`),
  KEY `fk.sales_channel.analytics_id` (`analytics_id`),
  CONSTRAINT `fk.sales_channel.analytics_id` FOREIGN KEY (`analytics_id`) REFERENCES `sales_channel_analytics` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.footer_category_id` FOREIGN KEY (`footer_category_id`, `footer_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.header_footer_id` FOREIGN KEY (`mail_header_footer_id`) REFERENCES `mail_header_footer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.home_cms_page` FOREIGN KEY (`home_cms_page_id`, `home_cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.hreflang_default_domain_id` FOREIGN KEY (`hreflang_default_domain_id`) REFERENCES `sales_channel_domain` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.navigation_category_id` FOREIGN KEY (`navigation_category_id`, `navigation_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.service_category_id` FOREIGN KEY (`service_category_id`, `service_category_version_id`) REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel.type_id` FOREIGN KEY (`type_id`) REFERENCES `sales_channel_type` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel.configuration` CHECK (json_valid(`configuration`)),
  CONSTRAINT `json.sales_channel.payment_method_ids` CHECK (json_valid(`payment_method_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel_analytics` (
            `id` binary(16
        ) NOT NULL,
  `tracking_id` varchar(50) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `track_orders` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `anonymize_ip` tinyint(1) NOT NULL DEFAULT 1,
  `track_offcanvas_cart` tinyint(1) NOT NULL DEFAULT 0,
  `enhanced_conversions` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel_country` (
            `sales_channel_id` binary(16
        ) NOT NULL,
  `country_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`country_id`),
  KEY `fk.sales_channel_country.country_id` (`country_id`),
  CONSTRAINT `fk.sales_channel_country.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_country.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel_currency` (
            `sales_channel_id` binary(16
        ) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`currency_id`),
  KEY `fk.sales_channel_currency.currency_id` (`currency_id`),
  CONSTRAINT `fk.sales_channel_currency.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_currency.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel_domain` (
            `id` binary(16
        ) NOT NULL,
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
  CONSTRAINT `fk.sales_channel_domain.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_domain.snippet_set_id` FOREIGN KEY (`snippet_set_id`) REFERENCES `snippet_set` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_domain.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `channel_language` (
            `sales_channel_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`language_id`),
  KEY `fk.sales_channel_language.language_id` (`language_id`),
  CONSTRAINT `fk.sales_channel_language.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_language.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_block` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `cms_section_id` binary(16) DEFAULT NULL,
  `cms_section_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `position` int(11) NOT NULL,
  `section_position` varchar(50) DEFAULT \'main\',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_page` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_page_translation` (
            `cms_page_id` binary(16
        ) NOT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_section` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `cms_page_id` binary(16) NOT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `position` int(11) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT \'default\',
  `name` varchar(255) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `sizing_mode` varchar(255) NOT NULL DEFAULT \'boxed\',
  `mobile_behavior` varchar(255) NOT NULL DEFAULT \'wrap\',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_slot` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `cms_block_id` binary(16) NOT NULL,
  `cms_block_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `type` varchar(255) NOT NULL,
  `slot` varchar(255) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.cms_slot.cms_block_id` (`cms_block_id`,`cms_block_version_id`),
  CONSTRAINT `fk.cms_slot.cms_block_id` FOREIGN KEY (`cms_block_id`, `cms_block_version_id`) REFERENCES `cms_block` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `cms_slot_translation` (
            `cms_slot_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `consent_log` (
            `id` bigint(20
        ) unsigned NOT NULL AUTO_INCREMENT,
  `consent_name` varchar(100) NOT NULL,
  `timestamp` datetime(3) NOT NULL,
  `message` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.consent_log.history` (`consent_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `consent_state` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(100) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `state` varchar(20) NOT NULL,
  `actor` varchar(255) NOT NULL,
  `updated_at` datetime(3) NOT NULL,
  `revision` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.consent_state` (`name`,`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `content_stream` (
            `id` binary(16
        ) NOT NULL,
  `api_filter` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`api_filter`)),
  `invalid` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `internal` tinyint(1) NOT NULL DEFAULT 0,
  `display_as_group` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.product_stream.api_filter` CHECK (json_valid(`api_filter`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `content_stream_filter` (
            `id` binary(16
        ) NOT NULL,
  `product_stream_id` binary(16) NOT NULL,
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
  KEY `fk.product_stream_filter.product_stream_id` (`product_stream_id`),
  KEY `fk.product_stream_filter.parent_id` (`parent_id`),
  CONSTRAINT `fk.product_stream_filter.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `product_stream_filter` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_stream_filter.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.product_stream_filter.parameters` CHECK (json_valid(`parameters`)),
  CONSTRAINT `json.product_stream_filter.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `country` (
            `id` binary(16
        ) NOT NULL,
  `iso` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `iso3` varchar(45) DEFAULT NULL,
  `display_state_in_registration` tinyint(1) NOT NULL DEFAULT 0,
  `force_state_in_registration` tinyint(1) NOT NULL DEFAULT 0,
  `check_vat_id_pattern` tinyint(1) NOT NULL DEFAULT 0,
  `vat_id_pattern` varchar(255) DEFAULT NULL,
  `vat_id_required` tinyint(1) NOT NULL DEFAULT 0,
  `customer_tax` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`customer_tax`)),
  `company_tax` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`company_tax`)),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `country_state` (
            `id` binary(16
        ) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `short_code` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.country_state.country_id` (`country_id`),
  CONSTRAINT `fk.country_state.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `country_state_translation` (
            `country_state_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `country_translation` (
            `country_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `currency` (
            `id` binary(16
        ) NOT NULL,
  `iso_code` char(3) NOT NULL,
  `factor` double NOT NULL,
  `symbol` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `item_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`item_rounding`)),
  `total_rounding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`total_rounding`)),
  `tax_free_from` double DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.currency.iso_code` (`iso_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `currency_country_rounding` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `currency_translation` (
            `currency_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `custom_entity` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`)),
  `app_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`flags`)),
  `plugin_id` binary(16) DEFAULT NULL,
  `custom_fields_aware` tinyint(1) NOT NULL DEFAULT 0,
  `label_property` varchar(255) DEFAULT NULL,
  `deleted_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `app_id` (`app_id`),
  KEY `fk.custom_entity.plugin_id` (`plugin_id`),
  CONSTRAINT `fk.custom_entity.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.custom_entity.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.custom_entity.fields` CHECK (json_valid(`fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `custom_field` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `set_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `allow_customer_write` tinyint(4) NOT NULL DEFAULT 0,
  `allow_cart_expose` tinyint(1) NOT NULL DEFAULT 0,
  `store_api_aware` tinyint(1) NOT NULL DEFAULT 1,
  `include_in_search` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.custom_field.name` (`name`),
  KEY `fk.custom_field.set_id` (`set_id`),
  CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (`set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.custom_field.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `custom_field_set` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `app_id` binary(16) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `global` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `extension_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.custom_field_set.app_id` (`app_id`),
  CONSTRAINT `fk.custom_field_set.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.custom_field_set.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `custom_field_set_relation` (
            `id` binary(16
        ) NOT NULL,
  `set_id` binary(16) NOT NULL,
  `entity_name` varchar(64) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.custom_field_set_relation.entity_name` (`set_id`,`entity_name`),
  CONSTRAINT `fk.custom_field_set_relation.set_id` FOREIGN KEY (`set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `customer_group_registration_sales_channels` (
            `customer_group_id` binary(16
        ) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`customer_group_id`,`sales_channel_id`),
  KEY `fk.customer_group_registration_sales_channels.customer_group_id` (`customer_group_id`),
  KEY `fk.customer_group_registration_sales_channels.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.customer_group_registration_sales_channels.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_group_registration_sales_channels.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `customer_group_translation` (
            `customer_group_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `registration_title` varchar(255) DEFAULT NULL,
  `registration_introduction` longtext DEFAULT NULL,
  `registration_only_company_registration` tinyint(1) DEFAULT NULL,
  `registration_seo_meta_description` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`customer_group_id`,`language_id`),
  KEY `fk.customer_group_translation.language_id` (`language_id`),
  CONSTRAINT `fk.customer_group_translation.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_group_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.customer_group_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `customer_wishlist` (
            `id` binary(16
        ) NOT NULL,
  `customer_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.customer_wishlist` (`sales_channel_id`,`customer_id`),
  KEY `fk.customer_wishlist.sales_channel_id` (`sales_channel_id`),
  KEY `fk.customer_wishlist.customer_id` (`customer_id`),
  CONSTRAINT `fk.customer_wishlist.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_wishlist.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `customer_wishlist_product` (
            `id` binary(16
        ) NOT NULL,
  `customer_wishlist_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.customer_wishlist.sales_channel_id__customer_id` (`customer_wishlist_id`,`product_id`),
  KEY `fk.customer_wishlist_product.product_id` (`product_id`,`product_version_id`),
  KEY `fk.customer_wishlist_product.customer_wishlist_id` (`customer_wishlist_id`),
  CONSTRAINT `fk.customer_wishlist_product.customer_wishlist_id` FOREIGN KEY (`customer_wishlist_id`) REFERENCES `customer_wishlist` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_wishlist_product.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `deleted_apps` (
            `name` varchar(255
        ) NOT NULL,
  `app_secret` varchar(255) NOT NULL,
  `unconfirmed_app_secrets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unconfirmed_app_secrets`)),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `elasticsearch_index_task` (
            `id` binary(16
        ) NOT NULL,
  `index` varchar(500) NOT NULL,
  `alias` varchar(500) NOT NULL,
  `entity` varchar(500) NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `flow` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `event_name` varchar(255) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 1,
  `payload` longblob DEFAULT NULL,
  `invalid` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `app_flow_event_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.flow.event_name` (`event_name`,`priority`),
  KEY `fk.flow.app_flow_event_id` (`app_flow_event_id`),
  CONSTRAINT `fk.flow.app_flow_event_id` FOREIGN KEY (`app_flow_event_id`) REFERENCES `app_flow_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.flow.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `flow_sequence` (
            `id` binary(16
        ) NOT NULL,
  `flow_id` binary(16) NOT NULL,
  `app_flow_action_id` binary(16) DEFAULT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `rule_id` binary(16) DEFAULT NULL,
  `action_name` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `position` int(11) NOT NULL DEFAULT 1,
  `display_group` int(11) NOT NULL DEFAULT 1,
  `true_case` tinyint(1) NOT NULL DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.flow_sequence.flow_id` (`flow_id`),
  KEY `fk.flow_sequence.rule_id` (`rule_id`),
  KEY `fk.flow_sequence.parent_id` (`parent_id`),
  KEY `fk.flow_sequence.app_flow_action_id` (`app_flow_action_id`),
  CONSTRAINT `fk.flow_sequence.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.flow_sequence.flow_id` FOREIGN KEY (`flow_id`) REFERENCES `flow` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.flow_sequence.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `flow_sequence` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.flow_sequence.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.flow_sequence.config` CHECK (json_valid(`config`)),
  CONSTRAINT `json.flow_sequence.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `flow_template` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.flow_template.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `import_export_file` (
            `id` binary(16
        ) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `expire_date` datetime(3) NOT NULL,
  `size` int(11) DEFAULT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `import_export_log` (
            `id` binary(16
        ) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `records` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `profile_name` varchar(255) DEFAULT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `profile_id` binary(16) DEFAULT NULL,
  `file_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `invalid_records_log_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.import_export_log.user_id` (`user_id`),
  KEY `fk.import_export_log.profile_id` (`profile_id`),
  KEY `fk.import_export_log.invalid_records_log_id` (`invalid_records_log_id`),
  KEY `fk.import_export_log.file_id` (`file_id`),
  CONSTRAINT `fk.import_export_log.file_id` FOREIGN KEY (`file_id`) REFERENCES `import_export_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.import_export_log.invalid_records_log_id` FOREIGN KEY (`invalid_records_log_id`) REFERENCES `import_export_log` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.import_export_log.profile_id` FOREIGN KEY (`profile_id`) REFERENCES `import_export_profile` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.import_export_log.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `json.import_export_log.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `import_export_profile` (
            `id` binary(16
        ) NOT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `source_entity` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `delimiter` varchar(255) DEFAULT NULL,
  `enclosure` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT \'import-export\',
  `mapping` longtext DEFAULT NULL,
  `update_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`update_by`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `technical_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.import_export_profile.technical_name` (`technical_name`),
  CONSTRAINT `json.import_export_profile.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `import_export_profile_translation` (
            `import_export_profile_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`import_export_profile_id`,`language_id`),
  KEY `fk.import_export_profile_translation.language_id` (`language_id`),
  CONSTRAINT `fk.import_export_profile_translation.import_export_profile_id` FOREIGN KEY (`import_export_profile_id`) REFERENCES `import_export_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.import_export_profile_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `increment` (
            `pool` varchar(255
        ) NOT NULL,
  `cluster` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `count` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`pool`,`cluster`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `integration` (
            `id` binary(16
        ) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `secret_access_key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `last_usage_at` datetime(3) DEFAULT NULL,
  `deleted_at` datetime(3) DEFAULT NULL,
  `mcp_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mcp_allowlist`)),
  PRIMARY KEY (`id`),
  KEY `idx.access_key` (`access_key`),
  CONSTRAINT `json.integration.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `integration_role` (
            `integration_id` binary(16
        ) NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  PRIMARY KEY (`integration_id`,`acl_role_id`),
  KEY `fk.integration_acl_role.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.integration_acl_role.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.integration_acl_role.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `invalidation_tags` (
            `id` binary(16
        ) NOT NULL,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `landing_page` (
            `id` binary(16
        ) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `cms_page_id` binary(16) DEFAULT NULL,
  `cms_page_version_id` binary(16) NOT NULL DEFAULT x\'0fa91ce3e96a4bc2be4bd9ce752c3425\',
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`,`version_id`),
  KEY `fk.landing_page.cms_page_id` (`cms_page_id`,`cms_page_version_id`),
  CONSTRAINT `fk.landing_page.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `landing_page_sales_channel` (
            `landing_page_id` binary(16
        ) NOT NULL,
  `landing_page_version_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  PRIMARY KEY (`landing_page_id`,`landing_page_version_id`,`sales_channel_id`),
  KEY `fk.landing_page_sales_channel.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.landing_page_sales_channel.product_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`) REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.landing_page_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `landing_page_tag` (
            `landing_page_id` binary(16
        ) NOT NULL,
  `landing_page_version_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`landing_page_id`,`landing_page_version_id`,`tag_id`),
  KEY `fk.landing_page_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.landing_page_tag.landing_page_version_id__landing_page_id` FOREIGN KEY (`landing_page_id`, `landing_page_version_id`) REFERENCES `landing_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.landing_page_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `landing_page_translation` (
            `landing_page_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `language` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `locale` (
            `id` binary(16
        ) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `locale_translation` (
            `locale_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `log_entry` (
            `id` binary(16
        ) NOT NULL,
  `message` longtext NOT NULL,
  `level` smallint(6) NOT NULL,
  `channel` varchar(255) NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.log_entry.created_at` (`created_at`),
  CONSTRAINT `json.log_entry.context` CHECK (json_valid(`context`)),
  CONSTRAINT `json.log_entry.extra` CHECK (json_valid(`extra`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_header_footer` (
            `id` binary(16
        ) NOT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_header_footer_translation` (
            `mail_header_footer_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template` (
            `id` binary(16
        ) NOT NULL,
  `mail_template_type_id` binary(16) DEFAULT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `was_modified_by_user` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk.mail_template.mail_template_type_id` (`mail_template_type_id`),
  CONSTRAINT `fk.mail_template.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template_media` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template_translation` (
            `mail_template_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template_type` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `available_entities` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `template_data` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.mail_template_type.technical_name` (`technical_name`),
  CONSTRAINT `json.mail_template_type.available_entities` CHECK (json_valid(`available_entities`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template_type_translation` (
            `mail_template_type_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `main_category` (
            `id` binary(16
        ) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.main_category.sales_channel_product` (`product_id`,`product_version_id`,`sales_channel_id`),
  KEY `fk.main_category.sales_channel_id` (`sales_channel_id`),
  KEY `fk.main_category.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.main_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.main_category.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.main_category.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mcp_list_version` (
            `list` varchar(32
        ) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`list`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mcp_tool_result_cache` (
            `id` binary(16
        ) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `mime_type` varchar(127) NOT NULL DEFAULT \'application/json\',
  `content` longtext NOT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_tool_result_session` (`session_id`),
  KEY `idx.mcp_tool_result_cache.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `mcp_toolset_session` (
            `session_id` varchar(255
        ) NOT NULL,
  `toolset_name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`session_id`,`toolset_name`),
  KEY `idx_mcp_toolset_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `measurement_display_unit` (
            `id` binary(16
        ) NOT NULL,
  `measurement_system_id` binary(16) NOT NULL,
  `default` tinyint(1) NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `factor` double NOT NULL,
  `precision` int(11) NOT NULL DEFAULT 3,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.measurement_display_unit.short_name` (`short_name`),
  KEY `fk.measurement_display_unit.measurement_system_id` (`measurement_system_id`),
  CONSTRAINT `fk.measurement_display_unit.measurement_system_id` FOREIGN KEY (`measurement_system_id`) REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `measurement_display_unit_translation` (
            `name` varchar(255
        ) DEFAULT NULL,
  `measurement_display_unit_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`measurement_display_unit_id`,`language_id`),
  KEY `fk.measurement_display_unit_translation.language_id` (`language_id`),
  CONSTRAINT `fk.measurement_display_unit_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.measurement_display_unit_translation.unit_id` FOREIGN KEY (`measurement_display_unit_id`) REFERENCES `measurement_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.measurement_display_unit_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `measurement_system` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.measurement_system.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `measurement_system_translation` (
            `name` varchar(255
        ) DEFAULT NULL,
  `measurement_system_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`measurement_system_id`,`language_id`),
  KEY `fk.measurement_system_translation.language_id` (`language_id`),
  CONSTRAINT `fk.measurement_system_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.measurement_system_translation.measurement_system_id` FOREIGN KEY (`measurement_system_id`) REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.measurement_system_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media` (
            `id` binary(16
        ) NOT NULL,
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
  `file_hash` varchar(32) GENERATED ALWAYS AS (json_unquote(json_extract(`meta_data`,\'$.hash\'))) STORED,
  PRIMARY KEY (`id`),
  KEY `fk.media.user_id` (`user_id`),
  KEY `idx.media.file_extension` (`file_extension`),
  KEY `idx.media.file_name` (`file_name`(768)),
  KEY `idx.media.file_hash` (`file_hash`),
  KEY `idx.media.uploaded_at_created_at_id` (`uploaded_at`,`created_at`,`id`),
  KEY `idx.media.media_folder_id_created_at_id` (`media_folder_id`,`created_at`,`id`),
  CONSTRAINT `fk.media.media_folder_id` FOREIGN KEY (`media_folder_id`) REFERENCES `media_folder` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.media.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.media.meta_data` CHECK (json_valid(`meta_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_default_folder` (
            `id` binary(16
        ) NOT NULL,
  `entity` varchar(255) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.media_default_folder.entity` (`entity`),
  CONSTRAINT `json.media_default_folder.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_folder` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_folder_configuration` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_folder_configuration_media_thumbnail_size` (
            `media_folder_configuration_id` binary(16
        ) NOT NULL,
  `media_thumbnail_size_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_folder_configuration_id`,`media_thumbnail_size_id`),
  KEY `fk.media_folder_configuration_media_thumbnail_size.size_id` (`media_thumbnail_size_id`),
  CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.conf_id` FOREIGN KEY (`media_folder_configuration_id`) REFERENCES `media_folder_configuration` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.size_id` FOREIGN KEY (`media_thumbnail_size_id`) REFERENCES `media_thumbnail_size` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_tag` (
            `media_id` binary(16
        ) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_id`,`tag_id`),
  KEY `fk.media_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.media_tag.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.media_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_thumbnail` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_thumbnail_size` (
            `id` binary(16
        ) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.width` (`width`,`height`),
  CONSTRAINT `json.media_thumbnail_size.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `media_translation` (
            `media_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `messenger_messages` (
            `id` bigint(20
        ) NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue_name` (`queue_name`),
  KEY `available_at` (`available_at`),
  KEY `delivered_at` (`delivered_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `messenger_stats` (
            `id` bigint(20
        ) unsigned NOT NULL AUTO_INCREMENT,
  `message_type` varchar(255) NOT NULL,
  `time_in_queue` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `migration` (
            `class` varchar(255
        ) NOT NULL,
  `creation_timestamp` int(8) NOT NULL,
  `update` timestamp(6) NULL DEFAULT NULL,
  `update_destructive` timestamp(6) NULL DEFAULT NULL,
  `message` text DEFAULT NULL,
  PRIMARY KEY (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `notification` (
            `id` binary(16
        ) NOT NULL,
  `status` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `admin_only` tinyint(1) NOT NULL DEFAULT 0,
  `required_privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_privileges`)),
  `created_by_integration_id` binary(16) DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.notification.created_by_integration_id` (`created_by_integration_id`),
  KEY `fk.notification.created_by_user_id` (`created_by_user_id`),
  CONSTRAINT `fk.notification.created_by_integration_id` FOREIGN KEY (`created_by_integration_id`) REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.notification.created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range` (
            `id` binary(16
        ) NOT NULL,
  `type_id` binary(16) NOT NULL,
  `global` tinyint(1) NOT NULL,
  `pattern` varchar(255) NOT NULL,
  `start` int(8) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range_sales_channel` (
            `id` binary(16
        ) NOT NULL,
  `number_range_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) DEFAULT NULL,
  `number_range_type_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.numer_range_id__sales_channel_id` (`number_range_id`,`sales_channel_id`),
  KEY `fk.number_range_sales_channel.sales_channel_id` (`sales_channel_id`),
  KEY `fk.number_range_sales_channel.number_range_type_id` (`number_range_type_id`),
  CONSTRAINT `fk.number_range_sales_channel.number_range_id` FOREIGN KEY (`number_range_id`) REFERENCES `number_range` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_sales_channel.number_range_type_id` FOREIGN KEY (`number_range_type_id`) REFERENCES `number_range_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range_state` (
            `id` binary(16
        ) NOT NULL,
  `number_range_id` binary(16) NOT NULL,
  `last_value` int(8) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`number_range_id`),
  UNIQUE KEY `uniq.id` (`id`),
  KEY `idx.number_range_id` (`number_range_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range_translation` (
            `number_range_id` binary(16
        ) NOT NULL,
  `name` varchar(64) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `language_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`number_range_id`,`language_id`),
  KEY `fk.number_range_translation.language_id` (`language_id`),
  CONSTRAINT `fk.number_range_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_translation.number_range_id` FOREIGN KEY (`number_range_id`) REFERENCES `number_range` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.number_range_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range_type` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(64) DEFAULT NULL,
  `global` tinyint(1) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `number_range_type_translation` (
            `number_range_type_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `type_name` varchar(64) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`number_range_type_id`,`language_id`),
  KEY `fk.number_range_type_translation.language_id` (`language_id`),
  CONSTRAINT `fk.number_range_type_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_type_translation.number_range_type_id` FOREIGN KEY (`number_range_type_id`) REFERENCES `number_range_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.number_range_type_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `oauth_auth_code` (
            `id` binary(16
        ) NOT NULL,
  `code_id` varchar(80) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `client_id` varchar(255) NOT NULL,
  `issued_at` datetime(3) NOT NULL,
  `expires_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.oauth_auth_code.code_id` (`code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `oauth_user` (
            `id` binary(16
        ) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `user_sub` varchar(255) NOT NULL,
  `token` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`token`)),
  `expiry` datetime NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `user_sub` (`user_sub`),
  KEY `idx.oauth_user.user_sub` (`user_sub`),
  CONSTRAINT `fk.oauth_user.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `payment_token` (
            `token` char(32
        ) NOT NULL,
  `expires` datetime(3) NOT NULL,
  `consumed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `plugin` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `base_class` varchar(255) NOT NULL,
  `composer_name` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `managed_by_composer` tinyint(1) NOT NULL DEFAULT 0,
  `path` varchar(255) DEFAULT NULL,
  `autoload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`autoload`)),
  `author` varchar(255) DEFAULT NULL,
  `copyright` varchar(255) DEFAULT NULL,
  `license` varchar(255) DEFAULT NULL,
  `version` varchar(255) NOT NULL,
  `upgrade_version` varchar(255) DEFAULT NULL,
  `icon` mediumblob DEFAULT NULL,
  `installed_at` datetime(3) DEFAULT NULL,
  `upgraded_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.name` (`name`),
  UNIQUE KEY `uniq.baseClass` (`base_class`),
  CONSTRAINT `json.autoload` CHECK (json_valid(`autoload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `plugin_translation` (
            `plugin_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `manufacturer_link` text DEFAULT NULL,
  `support_link` text DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`plugin_id`,`language_id`),
  KEY `fk.plugin_translation.language_id` (`language_id`),
  CONSTRAINT `fk.plugin_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.plugin_translation.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.plugin_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_category_tree` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`category_id`,`category_version_id`),
  KEY `fk.product_category_tree.category_id` (`category_id`,`category_version_id`),
  CONSTRAINT `fk.product_category_tree.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_category_tree.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_custom_field_set` (
            `custom_field_set_id` binary(16
        ) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`custom_field_set_id`,`product_id`,`product_version_id`),
  KEY `fk.product_custom_field_set.product_id` (`product_id`,`product_version_id`),
  CONSTRAINT `fk.product_custom_field_set.custom_field_set_id` FOREIGN KEY (`custom_field_set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.product_custom_field_set.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_keyword_dictionary` (
            `id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `keyword` varchar(500) NOT NULL,
  `reversed` varchar(500) GENERATED ALWAYS AS (reverse(`keyword`)) STORED,
  PRIMARY KEY (`id`,`language_id`),
  UNIQUE KEY `uniq.language_id_keyword` (`language_id`,`keyword`),
  KEY `idx.product_keyword_dictionary.language_id` (`language_id`),
  CONSTRAINT `fk.product_keyword_dictionary.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_option` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `property_group_option_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`property_group_option_id`),
  KEY `fk.product_option.property_group_option_id` (`property_group_option_id`),
  CONSTRAINT `fk.product_option.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_option.property_group_option_id` FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_property` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `property_group_option_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`property_group_option_id`),
  KEY `fk.product_property.configuration_option_id` (`property_group_option_id`),
  CONSTRAINT `fk.product_property.configuration_option_id` FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_property.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_search_config` (
            `id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `and_logic` tinyint(1) NOT NULL DEFAULT 1,
  `min_search_length` smallint(6) NOT NULL DEFAULT 2,
  `excluded_terms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`excluded_terms`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.product_search_config.language_id` (`language_id`),
  CONSTRAINT `fk.product_search_config.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.product_search_config.excluded_terms` CHECK (json_valid(`excluded_terms`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_search_config_field` (
            `id` binary(16
        ) NOT NULL,
  `product_search_config_id` binary(16) NOT NULL,
  `custom_field_id` binary(16) DEFAULT NULL,
  `field` varchar(255) NOT NULL,
  `tokenize` tinyint(1) NOT NULL DEFAULT 0,
  `searchable` tinyint(1) NOT NULL DEFAULT 0,
  `ranking` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `use_exact_subfield` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.search_config_field.field__config_id` (`field`,`product_search_config_id`),
  KEY `fk.search_config_field.product_search_config_id` (`product_search_config_id`),
  KEY `fk.search_config_field.custom_field_id` (`custom_field_id`),
  CONSTRAINT `fk.search_config_field.custom_field_id` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.search_config_field.product_search_config_id` FOREIGN KEY (`product_search_config_id`) REFERENCES `product_search_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_sorting_translation` (
            `product_sorting_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`product_sorting_id`,`language_id`),
  KEY `fk.product_sorting_translation.language_id` (`language_id`),
  CONSTRAINT `fk.product_sorting_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_sorting_translation.product_sorting_id` FOREIGN KEY (`product_sorting_id`) REFERENCES `product_sorting` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_stream_mapping` (
            `product_id` binary(16
        ) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `product_stream_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`product_version_id`,`product_stream_id`),
  KEY `fk.product_stream_mapping.product_stream_id` (`product_stream_id`),
  CONSTRAINT `fk.product_stream_mapping.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_stream_mapping.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_stream_translation` (
            `product_stream_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`product_stream_id`,`language_id`),
  KEY `fk.product_stream_translation.language_id` (`language_id`),
  CONSTRAINT `fk.product_stream_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_stream_translation.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.product_stream_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `product_visibility` (
            `id` binary(16
        ) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `visibility` int(11) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.product_id__sales_channel_id` (`product_id`,`product_version_id`,`sales_channel_id`),
  KEY `idx.product_visibility.product_id` (`product_id`,`product_version_id`),
  KEY `idx.product_visibility.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.product_visibility.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.product_visibility.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `property_group` (
            `id` binary(16
        ) NOT NULL,
  `sorting_type` varchar(50) NOT NULL DEFAULT \'alphanumeric\',
  `display_type` varchar(50) NOT NULL DEFAULT \'text\',
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `filterable` tinyint(1) NOT NULL DEFAULT 1,
  `visible_on_product_detail_page` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `property_group_option` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `property_group_option_translation` (
            `property_group_option_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `property_group_translation` (
            `property_group_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `refresh_token` (
            `id` binary(16
        ) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `token_id` varchar(80) NOT NULL,
  `issued_at` datetime(3) NOT NULL,
  `expires_at` datetime(3) NOT NULL,
  `family_id` binary(16) DEFAULT NULL,
  `revoked_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.token_id` (`token_id`),
  KEY `idx.refresh_token.family_id` (`family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `rule` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(500) NOT NULL,
  `description` longtext DEFAULT NULL,
  `priority` int(11) NOT NULL,
  `payload` longblob DEFAULT NULL,
  `invalid` tinyint(1) NOT NULL DEFAULT 0,
  `areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`areas`)),
  `module_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`module_types`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.rule.module_types` CHECK (json_valid(`module_types`)),
  CONSTRAINT `json.rule.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `rule_condition` (
            `id` binary(16
        ) NOT NULL,
  `type` varchar(255) NOT NULL,
  `rule_id` binary(16) NOT NULL,
  `script_id` binary(16) DEFAULT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `position` int(11) NOT NULL DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.rule_condition.rule_id` (`rule_id`),
  KEY `fk.rule_condition.parent_id` (`parent_id`),
  KEY `fk.rule_condition.script_id` (`script_id`),
  CONSTRAINT `fk.rule_condition.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `rule_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.rule_condition.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.rule_condition.script_id` FOREIGN KEY (`script_id`) REFERENCES `app_script_condition` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.rule_condition.value` CHECK (json_valid(`value`)),
  CONSTRAINT `json.rule_condition.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `rule_tag` (
            `rule_id` binary(16
        ) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`rule_id`,`tag_id`),
  KEY `fk.rule_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.rule_tag.id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.rule_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_api_context` (
            `token` varchar(255
        ) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `sales_channel_id` binary(16) DEFAULT NULL,
  `customer_id` binary(16) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`token`),
  UNIQUE KEY `uniq.sales_channel_api_context.sales_channel_id_customer_id` (`sales_channel_id`,`customer_id`),
  KEY `fk.sales_channel_api_context.customer_id` (`customer_id`),
  CONSTRAINT `fk.sales_channel_api_context.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.sales_channel_api_context.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_file` (
            `id` binary(16
        ) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `file_family` varchar(64) NOT NULL,
  `file_name` varchar(512) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `template_overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_overrides`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.sales_channel_file.sc_id_family_file_name` (`sales_channel_id`,`file_family`,`file_name`),
  CONSTRAINT `fk.sales_channel_file.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_file.template_overrides` CHECK (json_valid(`template_overrides`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_payment_method` (
            `sales_channel_id` binary(16
        ) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`payment_method_id`),
  KEY `fk.sales_channel_payment_method.payment_method_id` (`payment_method_id`),
  CONSTRAINT `fk.sales_channel_payment_method.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_payment_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_shipping_method` (
            `sales_channel_id` binary(16
        ) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`,`shipping_method_id`),
  KEY `fk.sales_channel_shipping_method.shipping_method_id` (`shipping_method_id`),
  CONSTRAINT `fk.sales_channel_shipping_method.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sales_channel_shipping_method.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_tracking_customer` (
            `id` binary(16
        ) NOT NULL,
  `customer_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.sales_channel_tracking_customer.customer` (`customer_id`),
  KEY `idx.sales_channel_tracking_customer.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.sc_tracking_customer.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sc_tracking_customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_tracking_order` (
            `id` binary(16
        ) NOT NULL,
  `order_id` binary(16) NOT NULL,
  `order_version_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.sales_channel_tracking_order.order` (`order_id`,`order_version_id`),
  KEY `idx.sales_channel_tracking_order.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.sc_tracking_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.sc_tracking_order.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_translation` (
            `sales_channel_id` binary(16
        ) NOT NULL,
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
  CONSTRAINT `fk.sales_channel_translation.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.sales_channel_translation.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.sales_channel_translation.home_slot_config` CHECK (json_valid(`home_slot_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_type` (
            `id` binary(16
        ) NOT NULL,
  `cover_url` varchar(500) DEFAULT NULL,
  `icon_name` varchar(255) DEFAULT NULL,
  `screenshot_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`screenshot_urls`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.sales_channel_type.screenshot_urls` CHECK (json_valid(`screenshot_urls`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_type_translation` (
            `sales_channel_type_id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `scheduled_task` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `scheduled_task_class` varchar(512) NOT NULL,
  `run_interval` int(11) NOT NULL,
  `status` varchar(255) NOT NULL,
  `last_execution_time` datetime(3) DEFAULT NULL,
  `next_execution_time` datetime(3) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `default_run_interval` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.scheduled_task.scheduled_task_class` (`scheduled_task_class`),
  CONSTRAINT `check.scheduled_task.run_interval` CHECK (`run_interval` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `script` (
            `id` binary(16
        ) NOT NULL,
  `script` longtext NOT NULL,
  `hook` varchar(255) NOT NULL,
  `name` varchar(1024) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `app_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.app_script.hook` (`hook`),
  KEY `fk.app_script.app_id` (`app_id`),
  CONSTRAINT `fk.app_script.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `seo_url` (
            `id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) DEFAULT NULL,
  `foreign_key` binary(16) NOT NULL,
  `route_name` varchar(255) NOT NULL,
  `path_info` varchar(750) NOT NULL,
  `seo_path_info` varchar(750) NOT NULL,
  `is_canonical` tinyint(1) DEFAULT NULL,
  `is_modified` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.seo_url.seo_path_info` (`language_id`,`sales_channel_id`,`seo_path_info`),
  UNIQUE KEY `uniq.seo_url.foreign_key` (`language_id`,`sales_channel_id`,`foreign_key`,`route_name`,`is_canonical`),
  KEY `fk.seo_url.sales_channel_id` (`sales_channel_id`),
  KEY `idx.foreign_key` (`language_id`,`foreign_key`,`sales_channel_id`,`is_canonical`),
  KEY `idx.path_info` (`language_id`,`sales_channel_id`,`is_canonical`,`path_info`),
  KEY `idx.delete_query` (`foreign_key`,`sales_channel_id`),
  KEY `idx.seo_url.language_path` (`language_id`,`seo_path_info`),
  CONSTRAINT `fk.seo_url.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.seo_url.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.seo_url.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `seo_url_template` (
            `id` binary(16
        ) NOT NULL,
  `sales_channel_id` binary(16) DEFAULT NULL,
  `route_name` varchar(255) NOT NULL,
  `entity_name` varchar(64) NOT NULL,
  `template` varchar(750) DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `is_headless` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.seo_url_template.route_name` (`sales_channel_id`,`route_name`),
  CONSTRAINT `fk.seo_url_template.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.seo_url_template.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `snippet` (
            `id` binary(16
        ) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `snippet_set` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `base_file` varchar(255) NOT NULL,
  `iso` varchar(255) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.snippet_set.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `initial_state_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.state_machine.technical_name` (`technical_name`),
  KEY `fk.state_machine.initial_state_id` (`initial_state_id`),
  CONSTRAINT `fk.state_machine.initial_state_id` FOREIGN KEY (`initial_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine_history` (
            `id` binary(16
        ) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `entity_name` varchar(100) NOT NULL,
  `from_state_id` binary(16) NOT NULL,
  `to_state_id` binary(16) NOT NULL,
  `action_name` varchar(255) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `referenced_id` binary(16) NOT NULL,
  `referenced_version_id` binary(16) NOT NULL,
  `integration_id` binary(16) DEFAULT NULL,
  `internal_comment` text DEFAULT NULL,
  `source_type` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.state_machine_history.state_machine_id` (`state_machine_id`),
  KEY `fk.state_machine_history.from_state_id` (`from_state_id`),
  KEY `fk.state_machine_history.to_state_id` (`to_state_id`),
  KEY `fk.state_machine_history.user_id` (`user_id`),
  KEY `idx.state_machine_history.referenced_entity` (`referenced_id`,`referenced_version_id`),
  KEY `fk.state_machine_history.integration_id` (`integration_id`),
  CONSTRAINT `fk.state_machine_history.from_state_id` FOREIGN KEY (`from_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.to_state_id` FOREIGN KEY (`to_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine_state` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.technical_name_state_machine` (`technical_name`,`state_machine_id`),
  KEY `idx.state_machine_state.state_machine_id` (`state_machine_id`),
  CONSTRAINT `fk.state_machine_state.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine_state_translation` (
            `language_id` binary(16
        ) NOT NULL,
  `state_machine_state_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`language_id`,`state_machine_state_id`),
  KEY `idx.language` (`language_id`),
  KEY `idx.state_machine` (`state_machine_state_id`),
  CONSTRAINT `fk.state_machine_state_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_state_translation.state_machine_state_id` FOREIGN KEY (`state_machine_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.state_machine_state_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine_transition` (
            `id` binary(16
        ) NOT NULL,
  `action_name` varchar(255) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `from_state_id` binary(16) NOT NULL,
  `to_state_id` binary(16) NOT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.state_machine_transition.action_name_state_machine` (`action_name`,`state_machine_id`,`from_state_id`,`to_state_id`),
  KEY `idx.state_machine_transition.state_machine_id` (`state_machine_id`),
  KEY `idx.state_machine_transition.from_state_id` (`from_state_id`),
  KEY `idx.state_machine_transition.to_state_id` (`to_state_id`),
  CONSTRAINT `fk.state_machine_transition.from_state_id` FOREIGN KEY (`from_state_id`) REFERENCES `state_machine_state` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_transition.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_transition.to_state_id` FOREIGN KEY (`to_state_id`) REFERENCES `state_machine_state` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.state_machine_transition.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `state_machine_translation` (
            `language_id` binary(16
        ) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`language_id`,`state_machine_id`),
  KEY `idx.state_machine_translation.language` (`language_id`),
  KEY `idx.state_machine_translation.state_machine` (`state_machine_id`),
  CONSTRAINT `fk.state_machine_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.state_machine_translation.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.state_machine_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `system_config` (
            `id` binary(16
        ) NOT NULL,
  `configuration_key` varchar(255) NOT NULL,
  `configuration_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuration_value`)),
  `sales_channel_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.system_config.configuration_key__sales_channel_id` (`configuration_key`,`sales_channel_id`),
  KEY `fk.system_config.sales_channel_id` (`sales_channel_id`),
  CONSTRAINT `fk.system_config.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.system_config.configuration_value` CHECK (json_valid(`configuration_value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `tag` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme` (
            `id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `preview_media_id` binary(16) DEFAULT NULL,
  `parent_theme_id` binary(16) DEFAULT NULL,
  `base_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`base_config`)),
  `config_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_values`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `theme_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.theme.technical_name` (`technical_name`),
  KEY `fk.theme.preview_media_id` (`preview_media_id`),
  CONSTRAINT `fk.theme.preview_media_id` FOREIGN KEY (`preview_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.theme.base_config` CHECK (json_valid(`base_config`)),
  CONSTRAINT `json.theme.config_values` CHECK (json_valid(`config_values`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme_child` (
            `parent_id` binary(16
        ) NOT NULL,
  `child_id` binary(16) NOT NULL,
  PRIMARY KEY (`parent_id`,`child_id`),
  KEY `fk.theme_child.child_id` (`child_id`),
  CONSTRAINT `fk.theme_child.child_id` FOREIGN KEY (`child_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_child.parent_id__theme_id` FOREIGN KEY (`parent_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme_media` (
            `theme_id` binary(16
        ) NOT NULL,
  `media_id` binary(16) NOT NULL,
  PRIMARY KEY (`theme_id`,`media_id`),
  KEY `fk.theme_media.media_id` (`media_id`),
  CONSTRAINT `fk.theme_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_media.theme_id` FOREIGN KEY (`theme_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme_runtime_config` (
            `theme_id` binary(16
        ) NOT NULL,
  `technical_name` varchar(255) DEFAULT NULL,
  `resolved_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resolved_config`)),
  `view_inheritance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`view_inheritance`)),
  `script_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`script_files`)),
  `icon_sets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`icon_sets`)),
  `updated_at` datetime(3) NOT NULL,
  `import_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`import_map`)),
  PRIMARY KEY (`theme_id`),
  UNIQUE KEY `uidx.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme_sales_channel` (
            `theme_id` binary(16
        ) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`),
  KEY `fk.theme_sales_channel.theme_id` (`theme_id`),
  CONSTRAINT `fk.theme_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_sales_channel.theme_id` FOREIGN KEY (`theme_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `theme_translation` (
            `theme_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`labels`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `help_texts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`help_texts`)),
  PRIMARY KEY (`theme_id`,`language_id`),
  KEY `fk.theme_translation.language_id` (`language_id`),
  CONSTRAINT `fk.theme_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_translation.theme_id` FOREIGN KEY (`theme_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.theme_translation.labels` CHECK (json_valid(`labels`)),
  CONSTRAINT `json.theme_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `unit` (
            `id` binary(16
        ) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `unit_translation` (
            `unit_id` binary(16
        ) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `short_code` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`unit_id`,`language_id`),
  KEY `fk.unit_translation.language_id` (`language_id`),
  CONSTRAINT `fk.unit_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.unit_translation.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.unit_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `user` (
            `id` binary(16
        ) NOT NULL,
  `auto_increment` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_group_id` binary(16) NOT NULL,
  `requested_customer_group_id` binary(16) DEFAULT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `last_payment_method_id` binary(16) DEFAULT NULL,
  `default_billing_address_id` binary(16) NOT NULL,
  `default_shipping_address_id` binary(16) NOT NULL,
  `customer_number` varchar(255) NOT NULL,
  `salutation_id` binary(16) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `password` varchar(1024) DEFAULT NULL,
  `legacy_password` varchar(255) DEFAULT NULL,
  `legacy_encoder` varchar(255) DEFAULT NULL,
  `email` varchar(254) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `vat_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vat_ids`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `double_opt_in_registration` tinyint(1) NOT NULL DEFAULT 0,
  `double_opt_in_email_sent_date` datetime(3) DEFAULT NULL,
  `double_opt_in_confirm_date` datetime(3) DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `guest` tinyint(1) NOT NULL DEFAULT 0,
  `first_login` datetime(3) DEFAULT NULL,
  `last_login` datetime(3) DEFAULT NULL,
  `newsletter_sales_channel_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`newsletter_sales_channel_ids`)),
  `birthday` date DEFAULT NULL,
  `last_order_date` datetime(3) DEFAULT NULL,
  `order_count` int(5) NOT NULL DEFAULT 0,
  `order_total_amount` double DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `affiliate_code` varchar(255) DEFAULT NULL,
  `campaign_code` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `remote_address` varchar(255) DEFAULT NULL,
  `tag_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tag_ids`)),
  `bound_sales_channel_id` binary(16) DEFAULT NULL,
  `created_by_id` binary(16) DEFAULT NULL,
  `updated_by_id` binary(16) DEFAULT NULL,
  `account_type` varchar(255) NOT NULL DEFAULT \'private\',
  `review_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.auto_increment` (`auto_increment`),
  UNIQUE KEY `hash` (`hash`),
  KEY `idx.firstlogin` (`first_login`),
  KEY `idx.lastlogin` (`last_login`),
  KEY `idx.customer.default_billing_address_id` (`default_billing_address_id`),
  KEY `idx.customer.default_shipping_address_id` (`default_shipping_address_id`),
  KEY `fk.customer.customer_group_id` (`customer_group_id`),
  KEY `fk.customer.last_payment_method_id` (`last_payment_method_id`),
  KEY `fk.customer.sales_channel_id` (`sales_channel_id`),
  KEY `fk.customer.requested_customer_group_id` (`requested_customer_group_id`),
  KEY `fk.customer.bound_sales_channel_id` (`bound_sales_channel_id`),
  KEY `idx.email` (`email`),
  KEY `fk.customer.salutation_id` (`salutation_id`),
  KEY `fk.customer.created_by_id` (`created_by_id`),
  KEY `fk.customer.updated_by_id` (`updated_by_id`),
  CONSTRAINT `fk.customer.bound_sales_channel_id` FOREIGN KEY (`bound_sales_channel_id`) REFERENCES `sales_channel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`) REFERENCES `payment_method` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.salutation_id` FOREIGN KEY (`salutation_id`) REFERENCES `salutation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.customer.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.customer.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.customer.tag_ids` CHECK (json_valid(`tag_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `user_address` (
            `id` binary(16
        ) NOT NULL,
  `customer_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `salutation_id` binary(16) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `street` varchar(255) NOT NULL,
  `zipcode` varchar(50) DEFAULT NULL,
  `city` varchar(70) NOT NULL,
  `phone_number` varchar(40) DEFAULT NULL,
  `additional_address_line1` varchar(255) DEFAULT NULL,
  `additional_address_line2` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk.customer_address.country_id` (`country_id`),
  KEY `fk.customer_address.country_state_id` (`country_state_id`),
  KEY `fk.customer_address.customer_id` (`customer_id`),
  KEY `fk.customer_address.salutation_id` (`salutation_id`),
  CONSTRAINT `fk.customer_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_address.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_address.salutation_id` FOREIGN KEY (`salutation_id`) REFERENCES `salutation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.customer_address.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `user_group` (
            `id` binary(16
        ) NOT NULL,
  `display_gross` tinyint(1) NOT NULL DEFAULT 1,
  `registration_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `user_recovery` (
            `id` binary(16
        ) NOT NULL,
  `customer_id` binary(16) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.customer_recovery.customer_id` (`customer_id`),
  CONSTRAINT `fk.customer_recovery.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `user_tag` (
            `customer_id` binary(16
        ) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`customer_id`,`tag_id`),
  KEY `fk.customer_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.customer_tag.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.customer_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `version` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.version.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `version_commit` (
            `id` binary(16
        ) NOT NULL,
  `auto_increment` bigint(20) NOT NULL AUTO_INCREMENT,
  `is_merge` tinyint(1) NOT NULL DEFAULT 0,
  `message` varchar(5000) DEFAULT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `integration_id` binary(16) DEFAULT NULL,
  `version_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_increment` (`auto_increment`),
  KEY `idx.version_commit.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `version_commit_data` (
            `id` binary(16
        ) NOT NULL,
  `auto_increment` bigint(20) NOT NULL AUTO_INCREMENT,
  `version_commit_id` binary(16) NOT NULL,
  `entity_name` varchar(100) NOT NULL,
  `entity_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`entity_id`)),
  `action` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `user_id` binary(16) DEFAULT NULL,
  `integration_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auto_increment` (`auto_increment`),
  KEY `fk.version_commit_data.version_commit_id` (`version_commit_id`),
  CONSTRAINT `fk.version_commit_data.version_commit_id` FOREIGN KEY (`version_commit_id`) REFERENCES `version_commit` (`id`) ON DELETE CASCADE,
  CONSTRAINT `json.version_commit_data.entity_id` CHECK (json_valid(`entity_id`)),
  CONSTRAINT `json.version_commit_data.payload` CHECK (json_valid(`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `webhook` (
            `id` binary(16
        ) NOT NULL,
  `name` varchar(255) NOT NULL,
  `event_name` varchar(500) NOT NULL,
  `url` varchar(500) NOT NULL,
  `app_id` binary(16) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `error_count` int(11) NOT NULL DEFAULT 0,
  `only_live_version` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `owner_user_id` binary(16) DEFAULT NULL,
  `owner_integration_id` binary(16) DEFAULT NULL,
  `acl_role_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acl_role_ids`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.webhook.name` (`name`,`app_id`),
  KEY `fk.webhook.app_id` (`app_id`),
  KEY `fk.webhook.owner_user_id` (`owner_user_id`),
  KEY `fk.webhook.owner_integration_id` (`owner_integration_id`),
  CONSTRAINT `fk.webhook.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.webhook.owner_integration_id` FOREIGN KEY (`owner_integration_id`) REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.webhook.owner_user_id` FOREIGN KEY (`owner_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `webhook_delivery` (
            `id` bigint(20
        ) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_event_log_id` binary(16) NOT NULL,
  `webhook_id` binary(16) DEFAULT NULL,
  `partition_key` binary(16) NOT NULL,
  `delivery_status` varchar(20) NOT NULL DEFAULT \'queued\',
  `execution_count` int(10) unsigned NOT NULL DEFAULT 0,
  `next_retry_at` datetime(3) DEFAULT NULL,
  `last_attempt_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.webhook_delivery.webhook_event_log_id` (`webhook_event_log_id`),
  KEY `idx.webhook_delivery.partition_status_retry` (`partition_key`,`delivery_status`,`next_retry_at`,`id`),
  KEY `idx.webhook_delivery.webhook_status` (`webhook_id`,`delivery_status`),
  CONSTRAINT `fk.webhook_delivery.webhook_event_log_id` FOREIGN KEY (`webhook_event_log_id`) REFERENCES `webhook_event_log` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.webhook_delivery.webhook_id` FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `webhook_event_log` (
            `id` binary(16
        ) NOT NULL,
  `app_name` varchar(255) DEFAULT NULL,
  `webhook_name` text NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `delivery_status` varchar(255) NOT NULL,
  `timestamp` int(11) DEFAULT NULL,
  `processing_time` int(11) DEFAULT NULL,
  `app_version` varchar(255) DEFAULT NULL,
  `request_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_content`)),
  `response_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_content`)),
  `response_status_code` int(11) DEFAULT NULL,
  `response_reason_phrase` varchar(255) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `serialized_webhook_message` longblob DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `only_live_version` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `sequence` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.webhook_event_log.request_content` CHECK (json_valid(`request_content`)),
  CONSTRAINT `json.webhook_event_log.response_content` CHECK (json_valid(`response_content`)),
  CONSTRAINT `json.webhook_event_log.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `webhook_stream` (
            `id` binary(16
        ) NOT NULL,
  `partition_key` binary(16) NOT NULL,
  `locked_by` varchar(64) DEFAULT NULL,
  `lock_expires_at` datetime(3) DEFAULT NULL,
  `last_claimed_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.webhook_stream.partition_key` (`partition_key`),
  KEY `idx.webhook_stream.claim` (`last_claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
