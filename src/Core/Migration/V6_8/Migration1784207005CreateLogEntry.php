<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207005CreateLogEntry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `log_entry` (
    `id`         BINARY(16)                              NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `message`    LONGTEXT                               NOT NULL,
    `level`      SMALLINT                               NOT NULL,
    `channel`    VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `context`    JSON                                    NULL,
    `extra`      JSON                                    NULL,
    `created_at` DATETIME(3)                             NOT NULL,
    `updated_at` DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.log_entry.tenant_id` (`tenant_id`),
    KEY `idx.log_entry.created_at` (`created_at`),
    CONSTRAINT `fk.log_entry.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `json.log_entry.context` CHECK (JSON_VALID(`context`)),
    CONSTRAINT `json.log_entry.extra` CHECK (JSON_VALID(`extra`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
