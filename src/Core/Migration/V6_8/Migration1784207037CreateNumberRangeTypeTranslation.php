<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207037CreateNumberRangeTypeTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207037;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `number_range_type_translation` (
    `number_range_type_id` BINARY(16) NOT NULL,
    `language_id`          BINARY(16) NOT NULL,
    `type_name`            VARCHAR(64) NULL,
    `custom_fields`        JSON NULL,
    `created_at`           DATETIME(3) NOT NULL,
    `updated_at`           DATETIME(3) NULL,
    PRIMARY KEY (`number_range_type_id`, `language_id`),
    CONSTRAINT `fk.number_range_type_translation.number_range_type_id` FOREIGN KEY (`number_range_type_id`)
        REFERENCES `number_range_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.number_range_type_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `json.number_range_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
