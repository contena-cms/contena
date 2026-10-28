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
class Migration1742199551SalesChannelDomainMeasurementUnits extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742199551;
    }

    public function update(Connection $connection): void
    {
        $this->addMeasurementUnitsColumn($connection);
    }

    private function addMeasurementUnitsColumn(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel_domain', 'measurement_units')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_domain`
            ADD COLUMN `measurement_units` JSON NULL;
        ');
    }
}
