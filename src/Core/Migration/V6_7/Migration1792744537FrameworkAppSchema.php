<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * App system, plugins, scripts, webhooks, custom entities.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744537FrameworkAppSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744537;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app` (
  `id` binary(16) NOT NULL,
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
  `source_type` varchar(20) NOT NULL DEFAULT 'local',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_action_button` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_action_button_translation` (
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `app_action_button_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`app_action_button_id`,`language_id`),
  KEY `fk.app_action_button_translation.app_action_button_id` (`app_action_button_id`),
  KEY `fk.app_action_button_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_action_button_translation.app_action_button_id` FOREIGN KEY (`app_action_button_id`) REFERENCES `app_action_button` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_action_button_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_administration_snippet` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_cms_block` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_cms_block_translation` (
  `label` varchar(255) DEFAULT NULL,
  `app_cms_block_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_cms_block_id`,`language_id`),
  KEY `fk.app_cms_block_translation.app_cms_block_id` (`app_cms_block_id`),
  KEY `fk.app_cms_block_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_cms_block.app_cms_block_id` FOREIGN KEY (`app_cms_block_id`) REFERENCES `app_cms_block` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_cms_block.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_config` (
  `key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_feature` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_action` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_action_translation` (
  `app_flow_action_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_event` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_script_condition` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_script_condition_translation` (
  `app_script_condition_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`app_script_condition_id`,`language_id`),
  KEY `fk.app_script_condition_translation.app_script_condition_id` (`app_script_condition_id`),
  KEY `fk.app_script_condition_translation.language_id` (`language_id`),
  CONSTRAINT `fk.app_script_condition_translation.app_script_condition_id` FOREIGN KEY (`app_script_condition_id`) REFERENCES `app_script_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.app_script_condition_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_template` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_translation` (
  `app_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_entity` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mcp_list_version` (
  `list` varchar(32) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`list`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mcp_tool_result_cache` (
  `id` binary(16) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `mime_type` varchar(127) NOT NULL DEFAULT 'application/json',
  `content` longtext NOT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_tool_result_session` (`session_id`),
  KEY `idx.mcp_tool_result_cache.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mcp_toolset_session` (
  `session_id` varchar(255) NOT NULL,
  `toolset_name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`session_id`,`toolset_name`),
  KEY `idx_mcp_toolset_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `plugin` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `plugin_translation` (
  `plugin_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `script` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_channel` (
  `theme_id` binary(16) NOT NULL,
  `sales_channel_id` binary(16) NOT NULL,
  PRIMARY KEY (`sales_channel_id`),
  KEY `fk.theme_sales_channel.theme_id` (`theme_id`),
  CONSTRAINT `fk.theme_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_sales_channel.theme_id` FOREIGN KEY (`theme_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_child` (
  `parent_id` binary(16) NOT NULL,
  `child_id` binary(16) NOT NULL,
  PRIMARY KEY (`parent_id`,`child_id`),
  KEY `fk.theme_child.child_id` (`child_id`),
  CONSTRAINT `fk.theme_child.child_id` FOREIGN KEY (`child_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_child.parent_id__theme_id` FOREIGN KEY (`parent_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_media` (
  `theme_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  PRIMARY KEY (`theme_id`,`media_id`),
  KEY `fk.theme_media.media_id` (`media_id`),
  CONSTRAINT `fk.theme_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.theme_media.theme_id` FOREIGN KEY (`theme_id`) REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_runtime_config` (
  `theme_id` binary(16) NOT NULL,
  `technical_name` varchar(255) DEFAULT NULL,
  `resolved_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resolved_config`)),
  `view_inheritance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`view_inheritance`)),
  `script_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`script_files`)),
  `icon_sets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`icon_sets`)),
  `updated_at` datetime(3) NOT NULL,
  `import_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`import_map`)),
  PRIMARY KEY (`theme_id`),
  UNIQUE KEY `uidx.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `theme_translation` (
  `theme_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook` (
  `id` binary(16) NOT NULL,
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
  CONSTRAINT `fk.webhook.owner_user_id` FOREIGN KEY (`owner_user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_delivery` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_event_log_id` binary(16) NOT NULL,
  `webhook_id` binary(16) DEFAULT NULL,
  `partition_key` binary(16) NOT NULL,
  `delivery_status` varchar(20) NOT NULL DEFAULT 'queued',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_event_log` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_stream` (
  `id` binary(16) NOT NULL,
  `partition_key` binary(16) NOT NULL,
  `locked_by` varchar(64) DEFAULT NULL,
  `lock_expires_at` datetime(3) DEFAULT NULL,
  `last_claimed_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.webhook_stream.partition_key` (`partition_key`),
  KEY `idx.webhook_stream.claim` (`last_claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
