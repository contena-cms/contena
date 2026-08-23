<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784272020CreateMessengerStats extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784272020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `messenger_stats` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_type`  VARCHAR(255)    NOT NULL,
    `time_in_queue` INT             NOT NULL,
    `created_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
