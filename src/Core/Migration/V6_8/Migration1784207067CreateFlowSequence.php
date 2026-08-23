<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207067CreateFlowSequence extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207067;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `flow_sequence` (
                `id` BINARY(16) NOT NULL,
                `tenant_id` BINARY(16) NULL,
                `flow_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `rule_id` BINARY(16) NULL,
                `action_name` VARCHAR(255) NULL,
                `config` JSON NULL,
                `position` INT NOT NULL DEFAULT 1,
                `display_group` INT NOT NULL DEFAULT 1,
                `true_case` TINYINT(1) NOT NULL DEFAULT 0,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.flow_sequence.tenant_id` (`tenant_id`),
                INDEX `idx.flow_sequence.flow_id` (`flow_id`),
                CONSTRAINT `json.flow_sequence.config` CHECK (JSON_VALID(`config`)),
                CONSTRAINT `json.flow_sequence.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.flow_sequence.flow_id` FOREIGN KEY (`flow_id`) REFERENCES `flow` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.flow_sequence.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.flow_sequence.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `flow_sequence` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.flow_sequence.tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
