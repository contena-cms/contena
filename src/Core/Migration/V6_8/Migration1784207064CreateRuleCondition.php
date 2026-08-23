<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207064CreateRuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207064;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `rule_condition` (
                `id` BINARY(16) NOT NULL,
                `tenant_id` BINARY(16) NULL,
                `type` VARCHAR(255) NOT NULL,
                `rule_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `value` JSON NULL,
                `position` INT NOT NULL DEFAULT 0,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.rule_condition.tenant_id` (`tenant_id`),
                INDEX `idx.rule_condition.rule_id` (`rule_id`),
                CONSTRAINT `json.rule_condition.value` CHECK (JSON_VALID(`value`)),
                CONSTRAINT `json.rule_condition.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.rule_condition.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.rule_condition.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `rule_condition` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.rule_condition.tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
