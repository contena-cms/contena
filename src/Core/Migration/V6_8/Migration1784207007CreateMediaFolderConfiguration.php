<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207007CreateMediaFolderConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207007;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_folder_configuration` (
    `id`                       BINARY(16) NOT NULL,
    `tenant_id`                BINARY(16) NULL,
    `create_thumbnails`        TINYINT(1) DEFAULT 1,
    `keep_aspect_ratio`        TINYINT(1) DEFAULT 1,
    `thumbnail_quality`        INT(11)    DEFAULT 80,
    `private`                  TINYINT(1) DEFAULT 0,
    `no_association`           TINYINT(1) NULL,
    `media_thumbnail_sizes_ro` LONGBLOB   NULL,
    `custom_fields`            JSON       NULL,
    `created_at`               DATETIME(3) NOT NULL,
    `updated_at`               DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.media_folder_configuration.tenant_id` (`tenant_id`),
    CONSTRAINT `json.media_folder_configuration.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.media_folder_configuration.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
