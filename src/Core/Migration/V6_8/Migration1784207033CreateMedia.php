<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207033CreateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207033;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media` (
    `id`              BINARY(16)                                 NOT NULL,
    `tenant_id`       BINARY(16)                                 NULL,
    `user_id`         BINARY(16)                                 NULL,
    `media_folder_id` BINARY(16)                                 NULL,
    `mime_type`       VARCHAR(255) COLLATE utf8mb4_unicode_ci    NULL,
    `file_extension`  VARCHAR(50) COLLATE utf8mb4_unicode_ci     NULL,
    `file_size`       INT(10) UNSIGNED                           NULL,
    `meta_data`       JSON                                       NULL,
    `file_name`       LONGTEXT COLLATE utf8mb4_unicode_ci        NULL,
    `media_type`      LONGBLOB                                   NULL,
    `thumbnails_ro`   LONGBLOB                                   NULL,
    `private`         TINYINT(1)                                 NOT NULL DEFAULT 0,
    `uploaded_at`     DATETIME(3)                                NULL,
    `config`          JSON                                       NULL,
    `path`            VARCHAR(2048) COLLATE utf8mb4_unicode_ci   NULL,
    `file_hash`       VARCHAR(32) GENERATED ALWAYS AS (
        JSON_UNQUOTE(JSON_EXTRACT(`meta_data`, '$.hash'))
    ) STORED,
    `created_at`      DATETIME(3)                                NOT NULL,
    `updated_at`      DATETIME(3)                                NULL,
    PRIMARY KEY (`id`),
    KEY `idx.media.tenant_id` (`tenant_id`),
    INDEX `idx.media.file_extension` (`file_extension`),
    INDEX `idx.media.file_name` (`file_name`(768)),
    INDEX `idx.media.file_hash` (`file_hash`),
    INDEX `idx.media.uploaded_at_created_at_id` (`uploaded_at`, `created_at`, `id`),
    INDEX `idx.media.media_folder_id_created_at_id` (`media_folder_id`, `created_at`, `id`),
    CONSTRAINT `json.media.meta_data` CHECK (JSON_VALID(`meta_data`)),
    CONSTRAINT `fk.media.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.media.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.media.media_folder_id` FOREIGN KEY (`media_folder_id`)
        REFERENCES `media_folder` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (!$this->foreignKeyExists($connection, 'user', 'fk.user.avatar_id')) {
            $connection->executeStatement(<<<'SQL'
ALTER TABLE `user`
    ADD CONSTRAINT `fk.user.avatar_id` FOREIGN KEY (`avatar_id`)
        REFERENCES `media` (`id`) ON DELETE SET NULL
SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
