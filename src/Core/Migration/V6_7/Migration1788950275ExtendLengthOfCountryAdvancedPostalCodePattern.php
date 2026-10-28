<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788950275;
    }

    public function update(Connection $connection): void
    {
        $column = TableHelper::getColumnOfTable(
            $connection,
            'country',
            'advanced_postal_code_pattern'
        );

        if ($column->type !== Types::STRING || $column->length === null || $column->length >= 1024) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `country`
            MODIFY COLUMN `advanced_postal_code_pattern` VARCHAR(1024) NULL
        ');
    }
}
