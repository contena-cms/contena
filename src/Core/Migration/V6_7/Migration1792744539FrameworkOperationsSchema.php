<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Queue, scheduling, SEO urls, import/export, flows, rules.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744539FrameworkOperationsSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744539;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `flow` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `flow_sequence` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `flow_template` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `json.flow_template.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_file` (
  `id` binary(16) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `expire_date` datetime(3) NOT NULL,
  `size` int(11) DEFAULT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_log` (
  `id` binary(16) NOT NULL,
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
  CONSTRAINT `fk.import_export_log.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `json.import_export_log.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_profile` (
  `id` binary(16) NOT NULL,
  `system_default` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `source_entity` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `delimiter` varchar(255) DEFAULT NULL,
  `enclosure` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'import-export',
  `mapping` longtext DEFAULT NULL,
  `update_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`update_by`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `technical_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.import_export_profile.technical_name` (`technical_name`),
  CONSTRAINT `json.import_export_profile.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `import_export_profile_translation` (
  `import_export_profile_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`import_export_profile_id`,`language_id`),
  KEY `fk.import_export_profile_translation.language_id` (`language_id`),
  CONSTRAINT `fk.import_export_profile_translation.import_export_profile_id` FOREIGN KEY (`import_export_profile_id`) REFERENCES `import_export_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.import_export_profile_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `messenger_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_type` varchar(255) NOT NULL,
  `time_in_queue` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range` (
  `id` binary(16) NOT NULL,
  `type_id` binary(16) NOT NULL,
  `global` tinyint(1) NOT NULL,
  `pattern` varchar(255) NOT NULL,
  `start` int(8) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_channel` (
  `id` binary(16) NOT NULL,
  `number_range_id` binary(16) NOT NULL,
  `channel_id` binary(16) DEFAULT NULL,
  `number_range_type_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.numer_range_id__channel_id` (`number_range_id`,`channel_id`),
  KEY `fk.number_range_channel.channel_id` (`channel_id`),
  KEY `fk.number_range_channel.number_range_type_id` (`number_range_type_id`),
  CONSTRAINT `fk.number_range_channel.number_range_id` FOREIGN KEY (`number_range_id`) REFERENCES `number_range` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_channel.number_range_type_id` FOREIGN KEY (`number_range_type_id`) REFERENCES `number_range_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.number_range_channel.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_state` (
  `id` binary(16) NOT NULL,
  `number_range_id` binary(16) NOT NULL,
  `last_value` int(8) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`number_range_id`),
  UNIQUE KEY `uniq.id` (`id`),
  KEY `idx.number_range_id` (`number_range_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_translation` (
  `number_range_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_type` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(64) DEFAULT NULL,
  `global` tinyint(1) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_type_translation` (
  `number_range_type_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `rule` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `rule_condition` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `rule_tag` (
  `rule_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`rule_id`,`tag_id`),
  KEY `fk.rule_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.rule_tag.id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.rule_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `scheduled_task` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `seo_url` (
  `id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `channel_id` binary(16) DEFAULT NULL,
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
  UNIQUE KEY `uniq.seo_url.seo_path_info` (`language_id`,`channel_id`,`seo_path_info`),
  UNIQUE KEY `uniq.seo_url.foreign_key` (`language_id`,`channel_id`,`foreign_key`,`route_name`,`is_canonical`),
  KEY `fk.seo_url.channel_id` (`channel_id`),
  KEY `idx.foreign_key` (`language_id`,`foreign_key`,`channel_id`,`is_canonical`),
  KEY `idx.path_info` (`language_id`,`channel_id`,`is_canonical`,`path_info`),
  KEY `idx.delete_query` (`foreign_key`,`channel_id`),
  KEY `idx.seo_url.language_path` (`language_id`,`seo_path_info`),
  CONSTRAINT `fk.seo_url.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.seo_url.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.seo_url.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `seo_url_template` (
  `id` binary(16) NOT NULL,
  `channel_id` binary(16) DEFAULT NULL,
  `route_name` varchar(255) NOT NULL,
  `entity_name` varchar(64) NOT NULL,
  `template` varchar(750) DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 1,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `is_headless` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.seo_url_template.route_name` (`channel_id`,`route_name`),
  CONSTRAINT `fk.seo_url_template.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.seo_url_template.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `initial_state_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.state_machine.technical_name` (`technical_name`),
  KEY `fk.state_machine.initial_state_id` (`initial_state_id`),
  CONSTRAINT `fk.state_machine.initial_state_id` FOREIGN KEY (`initial_state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_history` (
  `id` binary(16) NOT NULL,
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
  CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_state` (
  `id` binary(16) NOT NULL,
  `technical_name` varchar(255) NOT NULL,
  `state_machine_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.technical_name_state_machine` (`technical_name`,`state_machine_id`),
  KEY `idx.state_machine_state.state_machine_id` (`state_machine_id`),
  CONSTRAINT `fk.state_machine_state.state_machine_id` FOREIGN KEY (`state_machine_id`) REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_state_translation` (
  `language_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_transition` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_translation` (
  `language_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `unit` (
  `id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `unit_translation` (
  `unit_id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
