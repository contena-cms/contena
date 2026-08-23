<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207039CreateStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207039;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine_history` (
    `id`                    BINARY(16)  NOT NULL,
    `tenant_id`             BINARY(16)  NULL,
    `referenced_id`         BINARY(16)  NOT NULL,
    `referenced_version_id` BINARY(16)  NOT NULL,
    `state_machine_id`      BINARY(16)  NOT NULL,
    `entity_name`           VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `from_state_id`         BINARY(16)  NOT NULL,
    `to_state_id`           BINARY(16)  NOT NULL,
    `action_name`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_id`               BINARY(16)  NULL,
    `integration_id`        BINARY(16)  NULL,
    `internal_comment`      TEXT        NULL,
    `created_at`            DATETIME(3) NOT NULL,
    `updated_at`            DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx.state_machine_history.tenant_id` (`tenant_id`),
    INDEX `idx.state_machine_history.referenced_entity` (`referenced_id`, `referenced_version_id`),
    CONSTRAINT `fk.state_machine_history.state_machine_id` FOREIGN KEY (`state_machine_id`)
        REFERENCES `state_machine` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_history.from_state_id` FOREIGN KEY (`from_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_history.to_state_id` FOREIGN KEY (`to_state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_history.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_history.integration_id` FOREIGN KEY (`integration_id`)
        REFERENCES `integration` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.state_machine_history.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
