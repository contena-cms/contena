<?php declare(strict_types=1);

namespace Contena\Frontend\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1565640175RemoveChannelTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565640175;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `channel_theme`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
