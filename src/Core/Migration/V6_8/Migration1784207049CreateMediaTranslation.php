<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207049CreateMediaTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207049;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_translation` (
    `tenant_id`   BINARY(16)                              NULL,
    `media_id`     BINARY(16)                              NOT NULL,
    `language_id`  BINARY(16)                              NOT NULL,
    `alt`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `title`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields` JSON                                   NULL,
    `created_at`   DATETIME(3)                             NOT NULL,
    `updated_at`   DATETIME(3)                             NULL,
    PRIMARY KEY (`media_id`, `language_id`),
    KEY `idx.media_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `json.media_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.media_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.media_translation.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.media_translation.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
