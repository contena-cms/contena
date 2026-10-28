<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1775430000AddDisplayAsGroupToProductStream extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775430000;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_stream', 'display_as_group', 'TINYINT(1)', false, '1');
    }
}
