<?php declare(strict_types=1);

namespace Contena\Frontend\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1555406153ChannelTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555406153;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `channel_theme` (
              `channel_id`    BINARY(16)      NOT NULL,
              `theme_name`          VARCHAR(255)    NOT NULL,
              `created_at`          DATETIME(3)     NOT NULL,
              `updated_at`          DATETIME(3)     NULL,
              PRIMARY KEY (`channel_id`, `theme_name`),
              UNIQUE `uniq.channel_theme.channel_id` (`channel_id`),
              CONSTRAINT `fk.channel_theme.channel_id` FOREIGN KEY (`channel_id`)
                REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
