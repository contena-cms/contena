<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1782308630AddBusinessTimeZoneToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782308630;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, SalesChannelDefinition::ENTITY_NAME, 'business_time_zone')) {
            return;
        }

        $this->addColumn($connection, SalesChannelDefinition::ENTITY_NAME, 'business_time_zone', 'VARCHAR(255)');
    }
}
