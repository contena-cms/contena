<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1756305375AddCategoriesIndexToProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756305375;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'product', 'idx.product.categories')) {
            return;
        }

        $this->executeDdlStatement($connection, 'CREATE INDEX `idx.product.categories` ON `product` (`categories`)');
    }
}
