<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784274647CreateAppConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784274647;
    }

    public function update(Connection $connection): void
    {
        $statements = [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_config` (
    `key`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value` LONGTEXT COLLATE utf8mb4_unicode_ci     NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `path` VARCHAR(4096) COLLATE utf8mb4_unicode_ci NULL,
    `author` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `copyright` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `license` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `privacy` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `version` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `base_app_url` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 0,
    `allow_disable` TINYINT(1) NOT NULL DEFAULT 1,
    `configurable` TINYINT(1) NOT NULL DEFAULT 0,
    `icon` MEDIUMBLOB NULL,
    `app_secret` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `unconfirmed_app_secrets` JSON NULL,
    `allowed_hosts` JSON NULL,
    `integration_id` BINARY(16) NOT NULL,
    `acl_role_id` BINARY(16) NOT NULL,
    `template_load_priority` INT NULL DEFAULT 0,
    `self_managed` TINYINT(1) NOT NULL DEFAULT 0,
    `source_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
    `source_config` JSON NOT NULL DEFAULT (JSON_OBJECT()),
    `requested_privileges` JSON NOT NULL DEFAULT (JSON_ARRAY()),
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app.name` (`name`),
    KEY `fk.app.integration_id` (`integration_id`),
    KEY `fk.app.acl_role_id` (`acl_role_id`),
    CONSTRAINT `fk.app.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.app.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `deleted_apps` (
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `app_secret` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `unconfirmed_app_secrets` JSON NULL,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_translation` (
    `app_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `privacy_policy_extensions` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`app_id`, `language_id`),
    KEY `fk.app_translation.language_id` (`language_id`),
    CONSTRAINT `fk.app_translation.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.app_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_action_button` (
    `id` BINARY(16) NOT NULL,
    `entity` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `view` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `url` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `action` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_action_button.action` (`action`, `app_id`),
    KEY `fk.app_action_button.app_id` (`app_id`),
    CONSTRAINT `fk.app_action_button.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_action_button_translation` (
    `app_action_button_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`app_action_button_id`, `language_id`),
    KEY `fk.app_action_button_translation.language_id` (`language_id`),
    CONSTRAINT `fk.app_action_button_translation.app_action_button_id` FOREIGN KEY (`app_action_button_id`) REFERENCES `app_action_button` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.app_action_button_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `script` (
    `id` BINARY(16) NOT NULL,
    `script` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `hook` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
    `active` TINYINT(1) NOT NULL,
    `app_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.app_script.hook` (`hook`),
    KEY `fk.app_script.app_id` (`app_id`),
    CONSTRAINT `fk.app_script.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `event_name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `url` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `app_id` BINARY(16) NULL,
    `only_live_version` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.webhook.name` (`name`, `app_id`),
    KEY `fk.webhook.app_id` (`app_id`),
    CONSTRAINT `fk.webhook.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_event_log` (
    `id` BINARY(16) NOT NULL,
    `app_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `webhook_name` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `event_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `delivery_status` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `timestamp` INT NULL,
    `processing_time` INT NULL,
    `app_version` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `request_content` JSON NULL,
    `response_content` JSON NULL,
    `response_status_code` INT NULL,
    `response_reason_phrase` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `url` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `serialized_webhook_message` LONGBLOB NULL,
    `custom_fields` JSON NULL,
    `only_live_version` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `sequence` BIGINT UNSIGNED NULL,
    `failure_reason` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_delivery` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `webhook_event_log_id` BINARY(16) NOT NULL,
    `webhook_id` BINARY(16) NULL,
    `partition_key` BINARY(16) NOT NULL,
    `delivery_status` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
    `execution_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `next_retry_at` DATETIME(3) NULL,
    `last_attempt_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.webhook_delivery.webhook_event_log_id` (`webhook_event_log_id`),
    KEY `idx.webhook_delivery.partition_status_retry` (`partition_key`, `delivery_status`, `next_retry_at`, `id`),
    KEY `idx.webhook_delivery.webhook_status` (`webhook_id`, `delivery_status`),
    CONSTRAINT `fk.webhook_delivery.webhook_event_log_id` FOREIGN KEY (`webhook_event_log_id`) REFERENCES `webhook_event_log` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.webhook_delivery.webhook_id` FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_stream` (
    `id` BINARY(16) NOT NULL,
    `partition_key` BINARY(16) NOT NULL,
    `locked_by` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL,
    `lock_expires_at` DATETIME(3) NULL,
    `last_claimed_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.webhook_stream.partition_key` (`partition_key`),
    KEY `idx.webhook_stream.claim` (`last_claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `webhook_health` (
    `webhook_id` BINARY(16) NOT NULL,
    `endpoint_state` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'healthy',
    `consecutive_transient_failures` INT UNSIGNED NOT NULL DEFAULT 0,
    `consecutive_non_transient_failures` INT UNSIGNED NOT NULL DEFAULT 0,
    `degraded_cycle_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `cooldown_until` DATETIME(3) NULL,
    `suspended_since` DATETIME(3) NULL,
    `disabled_since` DATETIME(3) NULL,
    `disabled_origin` VARCHAR(16) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`webhook_id`),
    KEY `idx.webhook_health.probe_due` (`endpoint_state`, `cooldown_until`),
    KEY `idx.webhook_health.suspended_since` (`endpoint_state`, `suspended_since`),
    CONSTRAINT `fk.webhook_health.webhook_id` FOREIGN KEY (`webhook_id`) REFERENCES `webhook` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_feature` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NULL,
    `app_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `type` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `payload` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_feature.app_name_type_name` (`app_name`, `type`, `name`),
    KEY `idx.app_feature.type` (`type`),
    KEY `fk.app_feature.app_id` (`app_id`),
    CONSTRAINT `fk.app_feature.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_content_system_element_type` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `schema` JSON NOT NULL,
    `hash` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_content_system_element_type.name` (`name`),
    KEY `fk.app_content_system_element_type.app_id` (`app_id`),
    CONSTRAINT `fk.app_content_system_element_type.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_content_system_style_option` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `schema` JSON NOT NULL,
    `hash` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_content_system_style_option.name` (`name`),
    KEY `fk.app_content_system_style_option.app_id` (`app_id`),
    CONSTRAINT `fk.app_content_system_style_option.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_content_system_binding_specification` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `schema` JSON NOT NULL,
    `hash` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_content_system_binding_specification.app_id_name` (`app_id`, `name`),
    CONSTRAINT `fk.app_content_system_binding_specification.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_action` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `badge` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `url` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `delayable` TINYINT(1) NOT NULL DEFAULT 0,
    `parameters` JSON NULL,
    `config` JSON NULL,
    `headers` JSON NULL,
    `requirements` JSON NULL,
    `icon` MEDIUMBLOB NULL,
    `ct_icon` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_flow_action.name` (`name`),
    KEY `fk.app_flow_action.app_id` (`app_id`),
    CONSTRAINT `fk.app_flow_action.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_action_translation` (
    `app_flow_action_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `headline` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`app_flow_action_id`, `language_id`),
    KEY `fk.app_flow_action_translation.language_id` (`language_id`),
    CONSTRAINT `fk.app_flow_action_translation.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.app_flow_action_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_flow_event` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `aware` JSON NOT NULL,
    `custom_fields` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.app_flow_event.name` (`name`),
    KEY `fk.app_flow_event.app_id` (`app_id`),
    CONSTRAINT `fk.app_flow_event.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_script_condition` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `group` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `script` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `constraints` LONGBLOB NULL,
    `config` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `fk.app_script_condition.app_id` (`app_id`),
    CONSTRAINT `fk.app_script_condition.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_script_condition_translation` (
    `app_script_condition_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`app_script_condition_id`, `language_id`),
    KEY `fk.app_script_condition_translation.language_id` (`language_id`),
    CONSTRAINT `fk.app_script_condition_translation.app_script_condition_id` FOREIGN KEY (`app_script_condition_id`) REFERENCES `app_script_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.app_script_condition_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_template` (
    `id` BINARY(16) NOT NULL,
    `template` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `path` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
    `active` TINYINT(1) NOT NULL,
    `app_id` BINARY(16) NOT NULL,
    `hash` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.app_template.path` (`path`(256)),
    KEY `fk.app_template.app_id` (`app_id`),
    CONSTRAINT `fk.app_template.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_entity` (
    `id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `fields` JSON NOT NULL,
    `flags` JSON NULL,
    `app_id` BINARY(16) NULL,
    `plugin_id` BINARY(16) NULL,
    `custom_fields_aware` TINYINT(1) NOT NULL DEFAULT 0,
    `label_property` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `deleted_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.custom_entity.name` (`name`),
    KEY `fk.custom_entity.app_id` (`app_id`),
    KEY `fk.custom_entity.plugin_id` (`plugin_id`),
    CONSTRAINT `fk.custom_entity.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.custom_entity.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
ALTER TABLE `custom_field_set`
    ADD COLUMN `app_id` BINARY(16) NULL,
    ADD KEY `fk.custom_field_set.app_id` (`app_id`),
    ADD CONSTRAINT `fk.custom_field_set.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `flow`
    ADD COLUMN `app_flow_event_id` BINARY(16) NULL,
    ADD KEY `fk.flow.app_flow_event_id` (`app_flow_event_id`),
    ADD CONSTRAINT `fk.flow.app_flow_event_id` FOREIGN KEY (`app_flow_event_id`) REFERENCES `app_flow_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `flow_sequence`
    ADD COLUMN `app_flow_action_id` BINARY(16) NULL,
    ADD KEY `fk.flow_sequence.app_flow_action_id` (`app_flow_action_id`),
    ADD CONSTRAINT `fk.flow_sequence.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
SQL,
            <<<'SQL'
ALTER TABLE `rule_condition`
    ADD COLUMN `script_id` BINARY(16) NULL,
    ADD KEY `fk.rule_condition.script_id` (`script_id`),
    ADD CONSTRAINT `fk.rule_condition.script_id` FOREIGN KEY (`script_id`) REFERENCES `app_script_condition` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL,
        ];

        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
