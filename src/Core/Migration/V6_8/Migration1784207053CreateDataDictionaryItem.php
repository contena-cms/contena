<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207053CreateDataDictionaryItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207053;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `data_dictionary_item` (
    `id`            BINARY(16)                              NOT NULL,
    `dictionary_id` BINARY(16)                              NOT NULL,
    `parent_id`     BINARY(16)                              NULL,
    `child_count`   INT(11) UNSIGNED                        NOT NULL DEFAULT 0,
    `level`         INT(11) UNSIGNED                        NOT NULL DEFAULT 1,
    `path`          LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `code`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value`         JSON                                    NULL,
    `position`      INT(11)                                 NOT NULL DEFAULT 1,
    `active`        TINYINT(1)                              NOT NULL DEFAULT 1,
    `system_locked` TINYINT(1)                              NOT NULL DEFAULT 0,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.data_dictionary_item.dictionary_code` (`dictionary_id`, `code`),
    KEY `idx.data_dictionary_item.parent_id` (`parent_id`),
    KEY `idx.data_dictionary_item.dictionary_tree` (`dictionary_id`, `level`, `position`),
    KEY `idx.data_dictionary_item.dictionary_position` (`dictionary_id`, `position`),
    KEY `idx.data_dictionary_item.dictionary_active` (`dictionary_id`, `active`),
    CONSTRAINT `json.data_dictionary_item.value` CHECK (JSON_VALID(`value`)),
    CONSTRAINT `json.data_dictionary_item.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.data_dictionary_item.dictionary_id` FOREIGN KEY (`dictionary_id`)
        REFERENCES `data_dictionary` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.data_dictionary_item.parent_id` FOREIGN KEY (`parent_id`)
        REFERENCES `data_dictionary_item` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
