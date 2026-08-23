<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785746003CreateRegion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785746003;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `region` (
    `id`          BINARY(16)                              NOT NULL,
    `country_id`  BINARY(16)                              NOT NULL,
    `parent_id`   BINARY(16)                              NULL,
    `level`       INT(11) UNSIGNED                        NOT NULL DEFAULT 1,
    `type`        VARCHAR(32) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `code`        VARCHAR(64) COLLATE utf8mb4_unicode_ci  NULL,
    `path`        LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `child_count` INT(11)                                 NOT NULL DEFAULT 0,
    `position`    INT(11)                                 NOT NULL DEFAULT 1,
    `active`      TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields` JSON                                   NULL,
    `created_at`  DATETIME(3)                             NOT NULL,
    `updated_at`  DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.region.parent_id` (`parent_id`),
    KEY `idx.region.country_level_active` (`country_id`, `level`, `active`),
    KEY `idx.region.country_type_active` (`country_id`, `type`, `active`),
    KEY `idx.region.country_code` (`country_id`, `code`),
    CONSTRAINT `json.region.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.region.country_id` FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.region.parent_id` FOREIGN KEY (`parent_id`)
        REFERENCES `region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `region_translation` (
    `region_id`   BINARY(16)                              NOT NULL,
    `language_id` BINARY(16)                              NOT NULL,
    `name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `short_name`  VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields` JSON                                  NULL,
    `created_at`  DATETIME(3)                             NOT NULL,
    `updated_at`  DATETIME(3)                             NULL,
    PRIMARY KEY (`region_id`, `language_id`),
    CONSTRAINT `json.region_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.region_translation.region_id` FOREIGN KEY (`region_id`)
        REFERENCES `region` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.region_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->addColumn($connection, 'region', 'child_count', 'INT(11)', false, '0');

        $this->registerIndexer($connection, 'region.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
