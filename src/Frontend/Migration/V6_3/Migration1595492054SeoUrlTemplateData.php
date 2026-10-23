<?php declare(strict_types=1);

namespace Contena\Frontend\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class Migration1595492054SeoUrlTemplateData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595492054;
    }

    public function update(Connection $connection): void
    {
        $stmt = $connection->prepare('SELECT count(`id`) FROM seo_url_template WHERE `entity_name` = :entity_name AND `route_name` = :route_name');
        $result = StatementHelper::executeQuery($stmt, [
            'entity_name' => 'blog',
            'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
        ]);

        if ((int) $result->fetchOne() === 0) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'channel_id' => null,
                'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => 'blog',
                'template' => BlogPageSeoUrlRoute::DEFAULT_TEMPLATE,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $result = StatementHelper::executeQuery($stmt, [
            'entity_name' => 'category',
            'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
        ]);

        if ((int) $result->fetchOne() === 0) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'channel_id' => null,
                'route_name' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => 'category',
                'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
