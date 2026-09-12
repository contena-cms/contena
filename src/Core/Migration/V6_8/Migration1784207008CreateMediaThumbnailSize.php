<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

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
    `data_scope_id`       BINARY(16) NOT NULL,
    `width`           INT(11)    NOT NULL,
    `height`          INT(11)    NOT NULL,
    `custom_fields`   JSON       NULL,
    `created_at`      DATETIME(3) NOT NULL,
    `updated_at`      DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.media_thumbnail_size.data_scope_id` (`data_scope_id`),
    CONSTRAINT `uniq.media_thumbnail_size.scope_dimensions` UNIQUE (`data_scope_id`, `width`, `height`),
    CONSTRAINT `json.media_thumbnail_size.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.media_thumbnail_size.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
