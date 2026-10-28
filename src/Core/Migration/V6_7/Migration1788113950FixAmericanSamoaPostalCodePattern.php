<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class Migration1788113950FixAmericanSamoaPostalCodePattern extends MigrationStep
{
    private const BROKEN_PATTERN = '(96799)(?  :[ \\-](\\d{4}))?';

    private const FIXED_PATTERN = '(96799)(?:[ \\-](\\d{4}))?';

    public function getCreationTimestamp(): int
    {
        return 1788113950;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `country` SET `default_postal_code_pattern` = :fixed WHERE `default_postal_code_pattern` = :broken',
            ['fixed' => self::FIXED_PATTERN, 'broken' => self::BROKEN_PATTERN]
        );
    }
}
