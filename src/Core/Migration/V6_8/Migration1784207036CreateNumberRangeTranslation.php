<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207036CreateNumberRangeTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207036;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_translation` (
    `number_range_id` BINARY(16) NOT NULL,
    `name`            VARCHAR(64) NULL,
    `description`     VARCHAR(255) NULL,
    `custom_fields`   JSON NULL,
    `language_id`     BINARY(16) NOT NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`number_range_id`, `language_id`),
    CONSTRAINT `json.number_range_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.number_range_translation.number_range_id` FOREIGN KEY (`number_range_id`)
        REFERENCES `number_range` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.number_range_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
