<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class CacheInvalidationSubscriber
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
    ) {
    }

    public function invalidateConfig(): void
    {
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);
    }

    public function invalidateConfigKey(SystemConfigMultipleChangedEvent $event): void
    {
        $this->invalidateConfig();

        if ($event->isSilent()) {
            return;
        }

        $this->cacheInvalidator->invalidate(['system.config-' . $event->getChannelId()]);
    }

    /**
     * Covers SEO URLs written through the DAL, for example through the Administration API. The regeneration path
     * bypasses the DAL entirely, see invalidateCategoryRouteBySeoUrlUpdate().
     */
    public function invalidateCategoryRouteBySeoUrlChanges(EntityWrittenContainerEvent $event): void
    {
        $seoUrlIds = $event->getPrimaryKeys(SeoUrlDefinition::ENTITY_NAME);

        if ($seoUrlIds === []) {
            return;
        }

        // The join keeps Blog and LandingPage SEO URLs out; they do not carry a category breadcrumb.
        $categoryIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(seo_url.foreign_key)) AS category_id
             FROM seo_url
             INNER JOIN category ON category.id = seo_url.foreign_key AND category.version_id = :version
             WHERE seo_url.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($seoUrlIds), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($categoryIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), $categoryIds));
    }

    /**
     * `SeoUrlPersister` regenerates SEO URLs with a `MultiInsertQueryQueue` and raw statements, so no DAL write event
     * is dispatched. The event carries the affected foreign keys, which is also the only usable key here: the SEO URL
     * rows themselves are replaced or obsoleted by the time this runs.
     */
    public function invalidateCategoryRouteBySeoUrlUpdate(SeoUrlUpdateEvent $event): void
    {
        $foreignKeys = [];

        foreach ($event->getSeoUrls() as $seoUrl) {
            $foreignKey = $seoUrl['foreignKey'] ?? null;

            // A malformed entry must not abort the SEO URL regeneration this listener runs inside of.
            if (\is_string($foreignKey) && Uuid::isValid($foreignKey)) {
                $foreignKeys[$foreignKey] = true;
            }
        }

        if ($foreignKeys === []) {
            return;
        }

        // Keeps Blog and LandingPage SEO URLs out; they do not carry a category breadcrumb.
        $categoryIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) AS category_id
             FROM category
             WHERE id IN (:ids) AND version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($foreignKeys)),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($categoryIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), $categoryIds));
    }
}
