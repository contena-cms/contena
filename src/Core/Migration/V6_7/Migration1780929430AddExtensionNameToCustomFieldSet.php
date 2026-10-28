<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780929430AddExtensionNameToCustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780929430;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'custom_field_set', 'extension_name')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `custom_field_set`
            ADD COLUMN `extension_name` VARCHAR(255) NULL
        ');
    }
}
