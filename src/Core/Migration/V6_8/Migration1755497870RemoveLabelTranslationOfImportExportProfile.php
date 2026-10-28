<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Migration1755497870RemoveLabelTranslationOfImportExportProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1755497870;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropTableIfExists($connection, 'import_export_profile_translation');
    }
}
