<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784271960CreateMessengerMessages extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784271960;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `messenger_messages` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `body`         LONGTEXT     NOT NULL,
    `headers`      LONGTEXT     NOT NULL,
    `queue_name`   VARCHAR(190) NOT NULL,
    `created_at`   DATETIME     NOT NULL,
    `available_at` DATETIME     NOT NULL,
    `delivered_at` DATETIME     NULL,
    PRIMARY KEY (`id`),
    INDEX (`queue_name`),
    INDEX (`available_at`),
    INDEX (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
