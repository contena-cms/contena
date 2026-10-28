<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1740563553AddAppRequestedPrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1740563553;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'app',
            'requested_privileges',
            'json',
        );
    }
}
