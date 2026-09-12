<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1785210133CreateUserTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785210133;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_tag` (
    `data_scope_id` BINARY(16)  NOT NULL,
    `user_id`   BINARY(16)  NOT NULL,
    `tag_id`    BINARY(16)  NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`user_id`, `tag_id`),
    KEY `idx.user_tag.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.user_tag.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.user_tag.tag_id` FOREIGN KEY (`tag_id`)
        REFERENCES `tag` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.user_tag.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
