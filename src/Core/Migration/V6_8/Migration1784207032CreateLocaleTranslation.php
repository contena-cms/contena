<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207032CreateLocaleTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207032;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `locale_translation` (
    `locale_id`     BINARY(16)                              NOT NULL,
    `language_id`   BINARY(16)                              NOT NULL,
    `name`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `territory`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`locale_id`, `language_id`),
    CONSTRAINT `json.locale_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.locale_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.locale_translation.locale_id` FOREIGN KEY (`locale_id`)
        REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
