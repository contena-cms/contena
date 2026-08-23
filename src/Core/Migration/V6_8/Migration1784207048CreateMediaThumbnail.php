<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207048CreateMediaThumbnail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207048;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_thumbnail` (
    `id`                      BINARY(16)   NOT NULL,
    `tenant_id`               BINARY(16)   NULL,
    `media_id`                BINARY(16)   NOT NULL,
    `media_thumbnail_size_id` BINARY(16)   NOT NULL,
    `width`                   INT(10) UNSIGNED NOT NULL,
    `height`                  INT(10) UNSIGNED NOT NULL,
    `path`                    VARCHAR(2048) NULL,
    `custom_fields`           JSON         NULL,
    `created_at`              DATETIME(3)  NOT NULL,
    `updated_at`              DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.media_thumbnail.tenant_id` (`tenant_id`),
    CONSTRAINT `json.media_thumbnail.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.media_thumbnail.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.media_thumbnail.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.media_thumbnail.media_thumbnail_size_id` FOREIGN KEY (`media_thumbnail_size_id`)
        REFERENCES `media_thumbnail_size` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
