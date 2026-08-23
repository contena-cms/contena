<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207008CreateMediaThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207008;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_thumbnail_size` (
    `id`              BINARY(16) NOT NULL,
    `tenant_id`       BINARY(16) NULL,
    `width`           INT(11)    NOT NULL,
    `height`          INT(11)    NOT NULL,
    `platform_width`  INT(11)    GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `width`, NULL)) STORED,
    `platform_height` INT(11)    GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `height`, NULL)) STORED,
    `custom_fields`   JSON       NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.media_thumbnail_size.tenant_id` (`tenant_id`),
    CONSTRAINT `uniq.media_thumbnail_size.tenant_dimensions` UNIQUE (`tenant_id`, `width`, `height`),
    CONSTRAINT `uniq.media_thumbnail_size.platform_dimensions` UNIQUE (`platform_width`, `platform_height`),
    CONSTRAINT `json.media_thumbnail_size.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.media_thumbnail_size.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
