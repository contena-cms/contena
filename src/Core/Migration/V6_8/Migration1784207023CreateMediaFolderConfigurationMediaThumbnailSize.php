<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207023CreateMediaFolderConfigurationMediaThumbnailSize extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207023;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_folder_configuration_media_thumbnail_size` (
    `tenant_id`                    BINARY(16) NULL,
    `media_folder_configuration_id` BINARY(16) NOT NULL,
    `media_thumbnail_size_id`        BINARY(16) NOT NULL,
    PRIMARY KEY (`media_folder_configuration_id`, `media_thumbnail_size_id`),
    KEY `idx.media_folder_configuration_media_thumbnail_size.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.conf_id` FOREIGN KEY (`media_folder_configuration_id`)
        REFERENCES `media_folder_configuration` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.media_folder_configuration_media_thumbnail_size.size_id` FOREIGN KEY (`media_thumbnail_size_id`)
        REFERENCES `media_thumbnail_size` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
