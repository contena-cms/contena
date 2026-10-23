<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * ACL, admin users, integrations, consent, versioning.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744536FrameworkIdentitySchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744536;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `acl_role` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `privileges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`privileges`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `deleted_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `acl_user_role` (
  `user_id` binary(16) NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`acl_role_id`),
  KEY `fk.acl_user_role.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.acl_user_role.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk.acl_user_role.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_elasticsearch_index_task` (
  `id` binary(16) NOT NULL,
  `index` varchar(500) NOT NULL,
  `alias` varchar(500) NOT NULL,
  `entity` varchar(500) NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_user` (
  `id` binary(16) NOT NULL,
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
  `time_zone` varchar(255) NOT NULL DEFAULT 'UTC',
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `mcp_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mcp_allowlist`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.admin_user.email` (`email`),
  UNIQUE KEY `uniq.admin_user.username` (`username`),
  KEY `fk.admin_user.locale_id` (`locale_id`),
  KEY `fk.admin_user.avatar_id` (`avatar_id`),
  CONSTRAINT `fk.admin_user.avatar_id` FOREIGN KEY (`avatar_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk.admin_user.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `json.admin_user.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_user_access_key` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `secret_access_key` varchar(255) NOT NULL,
  `last_usage_at` datetime(3) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.admin_user_access_key.user_id_` (`user_id`),
  KEY `idx.admin_user_access_key.access_key` (`access_key`),
  CONSTRAINT `fk.admin_user_access_key.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.admin_user_access_key.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_user_config` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.user_id_key` (`user_id`,`key`),
  CONSTRAINT `fk.admin_user_config.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.admin_user_config.value` CHECK (json_valid(`value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_user_recovery` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.admin_user_recovery.user_id` (`user_id`),
  CONSTRAINT `fk.admin_user_recovery.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `consent_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consent_name` varchar(100) NOT NULL,
  `timestamp` datetime(3) NOT NULL,
  `message` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.consent_log.history` (`consent_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `consent_state` (
  `id` binary(16) NOT NULL,
  `name` varchar(100) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `state` varchar(20) NOT NULL,
  `actor` varchar(255) NOT NULL,
  `updated_at` datetime(3) NOT NULL,
  `revision` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.consent_state` (`name`,`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `deleted_apps` (
  `name` varchar(255) NOT NULL,
  `app_secret` varchar(255) NOT NULL,
  `unconfirmed_app_secrets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unconfirmed_app_secrets`)),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `increment` (
  `pool` varchar(255) NOT NULL,
  `cluster` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `count` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`pool`,`cluster`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `integration` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `integration_role` (
  `integration_id` binary(16) NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  PRIMARY KEY (`integration_id`,`acl_role_id`),
  KEY `fk.integration_acl_role.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.integration_acl_role.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.integration_acl_role.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `log_entry` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `migration` (
  `class` varchar(255) NOT NULL,
  `creation_timestamp` int(8) NOT NULL,
  `update` timestamp(6) NULL DEFAULT NULL,
  `update_destructive` timestamp(6) NULL DEFAULT NULL,
  `message` text DEFAULT NULL,
  PRIMARY KEY (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `notification` (
  `id` binary(16) NOT NULL,
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
  CONSTRAINT `fk.notification.created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `oauth_auth_code` (
  `id` binary(16) NOT NULL,
  `code_id` varchar(80) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `client_id` varchar(255) NOT NULL,
  `issued_at` datetime(3) NOT NULL,
  `expires_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.oauth_auth_code.code_id` (`code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `oauth_user` (
  `id` binary(16) NOT NULL,
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
  CONSTRAINT `fk.oauth_user.user_id` FOREIGN KEY (`user_id`) REFERENCES `admin_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `refresh_token` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `token_id` varchar(80) NOT NULL,
  `issued_at` datetime(3) NOT NULL,
  `expires_at` datetime(3) NOT NULL,
  `family_id` binary(16) DEFAULT NULL,
  `revoked_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.token_id` (`token_id`),
  KEY `idx.refresh_token.family_id` (`family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user` (
  `id` binary(16) NOT NULL,
  `auto_increment` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_group_id` binary(16) NOT NULL,
  `requested_user_group_id` binary(16) DEFAULT NULL,
  `channel_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `last_payment_method_id` binary(16) DEFAULT NULL,
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
  `double_opt_in_email_sent_date` datetime(3) DEFAULT NULL,
  `guest` tinyint(1) NOT NULL DEFAULT 0,
  `first_login` datetime(3) DEFAULT NULL,
  `newsletter_channel_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`newsletter_channel_ids`)),
  `last_order_date` datetime(3) DEFAULT NULL,
  `order_count` int(5) NOT NULL DEFAULT 0,
  `order_total_amount` double DEFAULT 0,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `remote_address` varchar(255) DEFAULT NULL,
  `tag_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tag_ids`)),
  `bound_channel_id` binary(16) DEFAULT NULL,
  `created_by_id` binary(16) DEFAULT NULL,
  `updated_by_id` binary(16) DEFAULT NULL,
  `account_type` varchar(255) NOT NULL DEFAULT 'private',
  `review_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.auto_increment` (`auto_increment`),
  KEY `idx.firstlogin` (`first_login`),
  KEY `fk.user.user_group_id` (`user_group_id`),
  KEY `fk.user.last_payment_method_id` (`last_payment_method_id`),
  KEY `fk.user.channel_id` (`channel_id`),
  KEY `fk.user.requested_user_group_id` (`requested_user_group_id`),
  KEY `fk.user.bound_channel_id` (`bound_channel_id`),
  KEY `idx.email` (`email`),
  KEY `fk.user.created_by_id` (`created_by_id`),
  KEY `fk.user.updated_by_id` (`updated_by_id`),
  CONSTRAINT `fk.user.bound_channel_id` FOREIGN KEY (`bound_channel_id`) REFERENCES `channel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.user.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk.user.user_group_id` FOREIGN KEY (`user_group_id`) REFERENCES `user_group` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.user.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `channel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.user.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `admin_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.user.custom_fields` CHECK (json_valid(`custom_fields`)),
  CONSTRAINT `json.user.tag_ids` CHECK (json_valid(`tag_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_address` (
  `id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
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
  KEY `fk.user_address.country_id` (`country_id`),
  KEY `fk.user_address.country_state_id` (`country_state_id`),
  CONSTRAINT `fk.user_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk.user_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `json.user_address.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_group` (
  `id` binary(16) NOT NULL,
  `display_gross` tinyint(1) NOT NULL DEFAULT 1,
  `registration_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_group_translation` (
  `user_group_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `registration_title` varchar(255) DEFAULT NULL,
  `registration_introduction` longtext DEFAULT NULL,
  `registration_only_company_registration` tinyint(1) DEFAULT NULL,
  `registration_seo_meta_description` longtext DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`user_group_id`,`language_id`),
  KEY `fk.user_group_translation.language_id` (`language_id`),
  CONSTRAINT `fk.user_group_translation.user_group_id` FOREIGN KEY (`user_group_id`) REFERENCES `user_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.user_group_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.user_group_translation.custom_fields` CHECK (json_valid(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_recovery` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.user_recovery.user_id` (`user_id`),
  CONSTRAINT `fk.user_recovery.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_tag` (
  `user_id` binary(16) NOT NULL,
  `tag_id` binary(16) NOT NULL,
  PRIMARY KEY (`user_id`,`tag_id`),
  KEY `fk.user_tag.tag_id` (`tag_id`),
  CONSTRAINT `fk.user_tag.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk.user_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `version` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx.version.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `version_commit` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `version_commit_data` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
