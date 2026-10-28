<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1788985964WidenSeoUrlRouteName extends MigrationStep
{
    private const COLUMN_LENGTH = 255;

    public function getCreationTimestamp(): int
    {
        return 1788985964;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, SeoUrlDefinition::ENTITY_NAME, 'route_name')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, SeoUrlDefinition::ENTITY_NAME, 'route_name');

        if ($column->type === 'string' && $column->length !== null && $column->length >= self::COLUMN_LENGTH) {
            return;
        }

        $this->executeDdlStatement(
            $connection,
            'ALTER TABLE `seo_url` MODIFY `route_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }
}
