<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
class Migration1784207029CreateAclUserRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207029;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `acl_user_role` (
    `tenant_id`   BINARY(16) NULL,
    `user_id`    BINARY(16) NOT NULL,
    `acl_role_id` BINARY(16) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`user_id`, `acl_role_id`),
    KEY `idx.acl_user_role.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.acl_user_role.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.acl_user_role.acl_role_id` FOREIGN KEY (`acl_role_id`)
        REFERENCES `acl_role` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.acl_user_role.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (!TableHelper::foreignKeyExists($connection, 'acl_role', 'fk.acl_role.created_by_id')) {
            $connection->executeStatement(<<<'SQL'
ALTER TABLE `acl_role`
    ADD CONSTRAINT `fk.acl_role.created_by_id` FOREIGN KEY (`created_by_id`)
        REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
