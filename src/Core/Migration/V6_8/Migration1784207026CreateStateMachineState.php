<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207026CreateStateMachineState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207026;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_state` (
    `id`               BINARY(16)                              NOT NULL,
    `technical_name`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `state_machine_id` BINARY(16)                              NOT NULL,
    `created_at`       DATETIME(3)                             NOT NULL,
    `updated_at`       DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.state_machine_state.state_machine_id` (`state_machine_id`),
    UNIQUE `uniq.technical_name_state_machine` (`technical_name`, `state_machine_id`),
    CONSTRAINT `fk.state_machine_state.state_machine_id` FOREIGN KEY (`state_machine_id`)
        REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        if (!$this->foreignKeyExists($connection, 'state_machine', 'fk.state_machine.initial_state_id')) {
            $connection->executeStatement(<<<'SQL'
ALTER TABLE `state_machine`
    ADD CONSTRAINT `fk.state_machine.initial_state_id` FOREIGN KEY (`initial_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
