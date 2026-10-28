<?php declare(strict_types=1);

namespace Contena\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1565640175RemoveSalesChannelTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565640175;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_theme`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
