<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1774359918ProductPriceQuantityRangeMinValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774359918;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `product_price` SET `quantity_start` = 1 WHERE `quantity_start` < 1');
        $connection->executeStatement('UPDATE `product_price` SET `quantity_end` = 1 WHERE `quantity_end` < 1');
    }
}
