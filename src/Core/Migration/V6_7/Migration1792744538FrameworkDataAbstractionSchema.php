<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Custom fields, elasticsearch index bookkeeping.
 *
 * Generated from the upstream final schema — do not edit manually.
 * Regenerate with scripts/generate-baseline-migration.py.
 */
class Migration1792744538FrameworkDataAbstractionSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792744538;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `set_id` binary(16) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  `allow_user_write` tinyint(4) NOT NULL DEFAULT 0,
  `allow_cart_expose` tinyint(1) NOT NULL DEFAULT 0,
  `store_api_aware` tinyint(1) NOT NULL DEFAULT 1,
  `include_in_search` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.custom_field.name` (`name`),
  KEY `fk.custom_field.set_id` (`set_id`),
  CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (`set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `json.custom_field.config` CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field_set` (
  `id` binary(16) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field_set_relation` (
  `id` binary(16) NOT NULL,
  `set_id` binary(16) NOT NULL,
  `entity_name` varchar(64) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq.custom_field_set_relation.entity_name` (`set_id`,`entity_name`),
  CONSTRAINT `fk.custom_field_set_relation.set_id` FOREIGN KEY (`set_id`) REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `elasticsearch_index_task` (
  `id` binary(16) NOT NULL,
  `index` varchar(500) NOT NULL,
  `alias` varchar(500) NOT NULL,
  `entity` varchar(500) NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `invalidation_tags` (
  `id` binary(16) NOT NULL,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function insert(Connection $connection): void
    {
    }
}
