<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1785327592AddTranslationAutoUpdateToLanguage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785327592;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, LanguageDefinition::ENTITY_NAME, 'translation_auto_update')) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `language`
            ADD COLUMN `translation_auto_update` TINYINT(1) NOT NULL DEFAULT 1
        SQL);
    }
}
