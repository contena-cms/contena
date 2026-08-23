<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

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
    `tenant_id`     BINARY(16)   NULL,
    `id`            BINARY(16)   NOT NULL,
    `code`          VARCHAR(255) NOT NULL,
    `platform_code` VARCHAR(255) GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `code`, NULL)) STORED,
    `created_by_id` BINARY(16)   NULL,
    `name`          VARCHAR(255) NOT NULL,
    `description`   LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `privileges`    JSON         NOT NULL,
    `deleted_at`    DATETIME(3)  NULL,
    `created_at`    DATETIME(3)  NOT NULL,
    `updated_at`    DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.acl_role.tenant_code` (`tenant_id`, `code`),
    UNIQUE KEY `uniq.acl_role.platform_code` (`platform_code`),
    KEY `idx.acl_role.created_by_id` (`created_by_id`),
    KEY `idx.acl_role.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.acl_role.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
