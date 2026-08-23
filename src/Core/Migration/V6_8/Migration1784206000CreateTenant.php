<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Development-baseline schema for Tenant, the platform-wide data-isolation
 * boundary. Tenants are platform-owned and are not tenant-scoped themselves.
 *
 * @internal
 */
class Migration1784206000CreateTenant extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784206000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant` (
    `id`            BINARY(16)                              NOT NULL,
    `name`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `code`          VARCHAR(64)  COLLATE utf8mb4_unicode_ci NOT NULL,
    `status`        TINYINT(1)                              NOT NULL DEFAULT 1,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`    DATETIME(3)                             NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.tenant.code` (`code`),
    CONSTRAINT `json.tenant.custom_fields` CHECK (JSON_VALID(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
