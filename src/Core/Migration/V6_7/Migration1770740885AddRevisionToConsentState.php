<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('data-services')]
class Migration1770740885AddRevisionToConsentState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770740885;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'consent_state', 'revision')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `consent_state`
            ADD COLUMN `revision` VARCHAR(255) NULL
        ');
    }
}
