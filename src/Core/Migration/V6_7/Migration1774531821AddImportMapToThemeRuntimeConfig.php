<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1774531821AddImportMapToThemeRuntimeConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774531821;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'theme_runtime_config', 'import_map', 'JSON');
    }
}
