<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829002RemoveRepairedDigitalProductStatesFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829002;
    }

    public function update(Connection $connection): void
    {
        $storage = new MySQLKeyValueStorage($connection);
        $storage->remove('core.repaired_digital_product_states');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
