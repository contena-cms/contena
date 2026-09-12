<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207062CreateMailTemplateMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207062;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_media` (
    `id`               BINARY(16) NOT NULL,
    `data_scope_id`        BINARY(16) NOT NULL,
    `mail_template_id` BINARY(16) NOT NULL,
    `language_id`      BINARY(16) NOT NULL,
    `media_id`         BINARY(16) NOT NULL,
    `position`         INT        NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx.mail_template_media.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.mail_template_media.mail_template_id`
        FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template_media.language_id`
        FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template_media.media_id`
        FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template_media.data_scope_id`
        FOREIGN KEY (`data_scope_id`) REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
