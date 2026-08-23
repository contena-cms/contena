<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207041CreateStateMachineTransition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207041;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_transition` (
    `id`               BINARY(16)                              NOT NULL,
    `action_name`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `state_machine_id` BINARY(16)                              NOT NULL,
    `from_state_id`    BINARY(16)                              NOT NULL,
    `to_state_id`      BINARY(16)                              NOT NULL,
    `custom_fields`    JSON                                    NULL,
    `created_at`       DATETIME(3)                             NOT NULL,
    `updated_at`       DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    KEY `idx.state_machine_transition.state_machine_id` (`state_machine_id`),
    KEY `idx.state_machine_transition.from_state_id` (`from_state_id`),
    KEY `idx.state_machine_transition.to_state_id` (`to_state_id`),
    UNIQUE `uniq.state_machine_transition.action_name_state_machine` (`action_name`, `state_machine_id`, `from_state_id`, `to_state_id`),
    CONSTRAINT `json.state_machine_transition.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.state_machine_transition.state_machine_id` FOREIGN KEY (`state_machine_id`)
        REFERENCES `state_machine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_transition.to_state_id` FOREIGN KEY (`to_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_transition.from_state_id` FOREIGN KEY (`from_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
