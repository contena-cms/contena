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
class Migration1743256470RemoveElasticsearchAppConfigFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743256470;
    }

    public function update(Connection $connection): void
    {
        $storage = new MySQLKeyValueStorage($connection);

        $storage->remove('ELASTIC_OPTIMIZE_FLAG');
    }
}
