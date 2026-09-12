<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207000CreateAclRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `acl_role` (
    `data_scope_id`     BINARY(16)   NOT NULL,
    `id`            BINARY(16)   NOT NULL,
    `code`          VARCHAR(255) NOT NULL,
    `created_by_id` BINARY(16)   NULL,
    `name`          VARCHAR(255) NOT NULL,
    `description`   LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `privileges`    JSON         NOT NULL,
    `deleted_at`    DATETIME(3)  NULL,
    `created_at`    DATETIME(3)  NOT NULL,
    `updated_at`    DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.acl_role.scope_code` (`data_scope_id`, `code`),
    KEY `idx.acl_role.created_by_id` (`created_by_id`),
    KEY `idx.acl_role.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.acl_role.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
