<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207020CreateIntegrationRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `integration_role` (
    `tenant_id`     BINARY(16) NULL,
    `integration_id` BINARY(16) NOT NULL,
    `acl_role_id`     BINARY(16) NOT NULL,
    PRIMARY KEY (`integration_id`, `acl_role_id`),
    KEY `idx.integration_role.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.integration_acl_role.acl_role_id` FOREIGN KEY (`acl_role_id`)
        REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.integration_acl_role.integration_id` FOREIGN KEY (`integration_id`)
        REFERENCES `integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.integration_role.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
