<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207054CreateDataDictionaryItemTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207054;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `data_dictionary_item_translation` (
    `data_dictionary_item_id` BINARY(16)                              NOT NULL,
    `language_id`               BINARY(16)                              NOT NULL,
    `label`                     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `description`               LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
    `created_at`                DATETIME(3)                             NOT NULL,
    `updated_at`                DATETIME(3)                             NULL,
    PRIMARY KEY (`data_dictionary_item_id`, `language_id`),
    KEY `idx.data_dictionary_item_translation.language_id` (`language_id`),
    CONSTRAINT `fk.data_dictionary_item_translation.data_dictionary_item_id` FOREIGN KEY (`data_dictionary_item_id`)
        REFERENCES `data_dictionary_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.data_dictionary_item_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
