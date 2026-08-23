<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207047CreateMediaTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207047;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `media_tag` (
    `tenant_id` BINARY(16) NULL,
    `media_id` BINARY(16) NOT NULL,
    `tag_id`   BINARY(16) NOT NULL,
    PRIMARY KEY (`media_id`, `tag_id`),
    KEY `idx.media_tag.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.media_tag.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.media_tag.media_id` FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.media_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
