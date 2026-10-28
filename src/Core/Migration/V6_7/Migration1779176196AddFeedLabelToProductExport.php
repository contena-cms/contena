<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1779176196AddFeedLabelToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779176196;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'feed_label', 'VARCHAR(20)');
    }
}
