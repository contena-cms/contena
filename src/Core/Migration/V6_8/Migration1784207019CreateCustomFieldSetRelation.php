<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207019CreateCustomFieldSetRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207019;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `custom_field_set_relation` (
    `id`          BINARY(16)  NOT NULL,
    `set_id`      BINARY(16)  NOT NULL,
    `entity_name` VARCHAR(64) NOT NULL,
    `created_at`  DATETIME(3) NOT NULL,
    `updated_at`  DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `uniq.custom_field_set_relation.entity_name` UNIQUE (`set_id`, `entity_name`),
    CONSTRAINT `fk.custom_field_set_relation.set_id` FOREIGN KEY (`set_id`)
        REFERENCES `custom_field_set` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
