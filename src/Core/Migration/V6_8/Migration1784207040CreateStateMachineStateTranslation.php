<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207040CreateStateMachineStateTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207040;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_state_translation` (
    `language_id`            BINARY(16)                              NOT NULL,
    `state_machine_state_id` BINARY(16)                              NOT NULL,
    `name`                   VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `custom_fields`          JSON                                    NULL,
    `created_at`             DATETIME(3)                             NOT NULL,
    `updated_at`             DATETIME(3)                             NULL,
    PRIMARY KEY (`language_id`, `state_machine_state_id`),
    KEY `idx.language` (`language_id`),
    KEY `idx.state_machine` (`state_machine_state_id`),
    CONSTRAINT `json.state_machine_state_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.state_machine_state_translation.state_machine_state_id` FOREIGN KEY (`state_machine_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_state_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
