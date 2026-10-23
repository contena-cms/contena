<?php declare(strict_types=1);

namespace Contena\Frontend\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1688644407ThemeAddThemeConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688644407;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'theme',
            column: 'theme_json',
            type: 'JSON',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
