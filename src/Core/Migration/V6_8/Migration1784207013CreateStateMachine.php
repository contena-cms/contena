<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207013CreateStateMachine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207013;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `state_machine` (
    `id`               BINARY(16)                              NOT NULL,
    `technical_name`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `initial_state_id` BINARY(16)                              NULL,
    `created_at`       DATETIME(3)                             NOT NULL,
    `updated_at`       DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE `uniq.state_machine.technical_name` (`technical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
