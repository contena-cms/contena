<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207065CreateRuleTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207065;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `rule_tag` (
                `data_scope_id` BINARY(16) NOT NULL,
                `rule_id` BINARY(16) NOT NULL,
                `tag_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`rule_id`, `tag_id`),
                INDEX `idx.rule_tag.data_scope_id` (`data_scope_id`),
                CONSTRAINT `fk.rule_tag.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.rule_tag.tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.rule_tag.data_scope_id` FOREIGN KEY (`data_scope_id`) REFERENCES `data_scope` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
