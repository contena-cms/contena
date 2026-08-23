<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207066CreateFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207066;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `flow` (
                `id` BINARY(16) NOT NULL,
                `tenant_id` BINARY(16) NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` VARCHAR(500) NULL,
                `event_name` VARCHAR(255) NOT NULL,
                `priority` INT NOT NULL DEFAULT 1,
                `payload` LONGBLOB NULL,
                `invalid` TINYINT(1) NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.flow.event_name` (`tenant_id`, `event_name`, `priority`),
                CONSTRAINT `json.flow.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.flow.tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
