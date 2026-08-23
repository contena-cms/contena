<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207034CreateNotification extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207034;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `notification` (
    `id`                        BINARY(16)   NOT NULL,
    `tenant_id`                 BINARY(16)   NULL,
    `status`                    VARCHAR(255) NOT NULL,
    `message`                   LONGTEXT     NOT NULL,
    `admin_only`                TINYINT(1)   NOT NULL DEFAULT 0,
    `required_privileges`       JSON         NULL,
    `created_by_integration_id` BINARY(16) DEFAULT NULL,
    `created_by_user_id`        BINARY(16)   NULL,
    `created_at`                DATETIME(3)  NOT NULL,
    `updated_at`                DATETIME(3)  NULL,
    PRIMARY KEY (`id`),
    KEY `idx.notification.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.notification.created_by_integration_id` FOREIGN KEY (`created_by_integration_id`)
        REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.notification.created_by_user_id` FOREIGN KEY (`created_by_user_id`)
        REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.notification.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
