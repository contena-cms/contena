<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Events\InvalidateBlogCache;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Channel\NavigationRoute;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamWriteResultHelper;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\Media\Channel\MediaRoute;
use Contena\Core\Content\Media\Event\MediaIndexerEvent;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelPaymentMethod\ChannelPaymentMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelShippingMethod\ChannelShippingMethodDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\Context\CachedBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\Channel\CountryStateRoute;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Currency\Channel\CurrencyRoute;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Language\Channel\LanguageRoute;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;

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
        private readonly bool $contentStreamIndexerEnabled,
    ) {
    }

    public function invalidateInitialStateIdLoader(EntityWrittenContainerEvent $event): void
    {
        if (!$event->getPrimaryKeys(StateMachineDefinition::ENTITY_NAME)) {
            return;
        }

        $this->cacheInvalidator->invalidate([InitialStateIdLoader::CACHE_KEY], true);
    }

    public function invalidateSitemap(SitemapGeneratedEvent $event): void
    {
        $this->cacheInvalidator->invalidate([
            SitemapRoute::buildName($event->getChannelContext()->getChannelId()),
        ]);
    }

    public function invalidateConfig(): void
    {
        // invalidates the complete cached config immediately
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);
    }

    public function invalidateConfigKey(SystemConfigChangedHook $event): void
    {
        // invalidates the complete cached config immediately
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);

        // SystemConfig is often uses to store internal values. In such cases clients can set silent flag to true,
        // so no http cache invalidations will happen.
        if ($event->silent) {
            return;
        }

        // global system config tag is used in all http caches that access system config, that should be invalidated delayed
        $this->cacheInvalidator->invalidate(['system.config-' . $event->channelId]);
    }

    public function invalidateSnippets(EntityWrittenContainerEvent $event): void
    {
        // invalidates all http cache items where the snippets used
        $snippets = $event->getEventByEntityName(SnippetDefinition::ENTITY_NAME);

        if (!$snippets) {
            return;
        }

        $setIds = $this->getSetIds($snippets->getIds());

        if ($setIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(Translator::tag(...), $setIds));
    }

    public function invalidateShippingMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // checks if a shipping method changed or the assignment between shipping method and sales channel
        $logs = [...$this->getChangedShippingMethods($event), ...$this->getChangedShippingAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidateRules(): void
    {
        // immediately invalidates the rule loader each time a rule changed or a plugin install state changed
        $this->cacheInvalidator->invalidate([CachedRuleLoader::CACHE_KEY], true);
    }

    public function invalidateCmsPageIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes and http cache pages where a cms page was loaded, the id is assigned as tag
        $ids = array_map(EntityCacheKeyGenerator::buildCmsTag(...), $event->getPrimaryKeys(CmsPageDefinition::ENTITY_NAME));
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCategoryRouteByCategoryTranslationChanges(EntityWrittenContainerEvent $event): void
    {
        $changedCategoryTranslations = $event->getPrimaryKeysWithPropertyChange(
            CategoryTranslationDefinition::ENTITY_NAME,
            ['slotConfig']
        );

        if ($changedCategoryTranslations === []) {
            return;
        }

        /** @var array<string, true> $categoryIds */
        $categoryIds = [];
        foreach ($changedCategoryTranslations as $primaryKey) {
            if (isset($primaryKey['categoryId']) && \is_string($primaryKey['categoryId'])) {
                $categoryIds[$primaryKey['categoryId']] = true;
            }
        }

        if ($categoryIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), array_keys($categoryIds)));
    }

    public function invalidateBlog(InvalidateBlogCache $event): void
    {
        $listing = array_map(BlogListingRoute::buildName(...), $this->getBlogCategoryIds($event->getIds()));

        $parents = array_map(BlogDetailRoute::buildName(...), $this->getParentIds($event->getIds()));

        $streams = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $this->getStreamIds($event->getIds()));

        $tags = array_merge($listing, $parents, $streams);

        $this->cacheInvalidator->invalidate($tags, force: $event->force);
    }

    public function invalidateBlogCrossSelling(EntityWrittenContainerEvent $event): void
    {
        $blogIds = $this->getChangedCrossSellingBlogIds($event);

        if ($blogIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(array_map(BlogDetailRoute::buildName(...), $blogIds), true);
    }

    public function invalidateBlogCrossSellingBeforeDeletion(EntityDeleteEvent $event): void
    {
        $blogIds = $this->getDeletedCrossSellingBlogIds($event);

        if ($blogIds === []) {
            return;
        }

        $event->addSuccess(
            fn () => $this->cacheInvalidator->invalidate(array_map(BlogDetailRoute::buildName(...), $blogIds), true)
        );
    }

    public function invalidateStreamIds(EntityWrittenContainerEvent $event): void
    {
        // invalidates all routes which are loaded based on a stream (e.G. category listing and cross selling)
        $streamIds = [
            ...$event->getPrimaryKeys(ContentStreamDefinition::ENTITY_NAME),
            ...ContentStreamWriteResultHelper::getAffectedStreamIds($event),
        ];

        $streamIds = array_values(array_unique($streamIds));

        if ($streamIds === []) {
            return;
        }

        $ids = array_map(EntityCacheKeyGenerator::buildStreamTag(...), $streamIds);
        $this->cacheInvalidator->invalidate($ids, force: true);
    }

    public function invalidateCategoryRouteByCategoryIds(CategoryIndexerEvent $event): void
    {
        // invalidates the category route cache when a category changed
        $this->cacheInvalidator->invalidate(array_map(CategoryRoute::buildName(...), $event->getIds()));
    }

    public function invalidateIndexedLandingPages(LandingPageIndexerEvent $event): void
    {
        // invalidates the landing page route, if the corresponding landing page changed
        $ids = array_map(LandingPageRoute::buildName(...), $event->getIds());
        $this->cacheInvalidator->invalidate($ids);
    }

    public function invalidateCurrencyRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the currency route when a currency changed or an assignment between the sales channel and currency changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCurrencyAssignments($event),
            ...$this->getChangedCurrencies($event),
        ]);
    }

    public function invalidateLanguageRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the language route when a language changed or an assignment between the sales channel and language changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedLanguageAssignments($event),
            ...$this->getChangedLanguages($event),
        ]);
    }

    public function invalidateCountryRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the country route when a country changed or an assignment between the sales channel and country changed
        $this->cacheInvalidator->invalidate([
            ...$this->getChangedCountryAssignments($event),
            ...$this->getChangedCountries($event),
        ]);
    }

    public function invalidateCountryStateRoute(EntityWrittenContainerEvent $event): void
    {
        $tags = [];
        if (
            $event->getDeletedPrimaryKeys(CountryStateDefinition::ENTITY_NAME)
            || $event->getPrimaryKeysWithPropertyChange(CountryStateDefinition::ENTITY_NAME, ['countryId'])
        ) {
            $tags[] = CountryStateRoute::ALL_TAG;
        }

        if ($tags === []) {
            // invalidates the country-state route when a state changed or an assignment between the state and country changed
            $tags = array_map(
                CountryStateRoute::buildName(...),
                $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME)
            );
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateSalutationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the salutation route when a salutation changed
        $this->cacheInvalidator->invalidate([...$this->getChangedSalutations($event)]);
    }

    public function invalidateNavigationRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the navigation route when a category changed or the entry point configuration of an sales channel changed
        $changedChannelSettings = $event->getPrimaryKeysWithPropertyChange(
            ChannelDefinition::ENTITY_NAME,
            ['navigationCategoryId', 'navigationCategoryDepth', 'serviceCategoryId', 'footerCategoryId']
        );
        if ($changedChannelSettings !== []) {
            // if the sales channel settings changed, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $changedCategoryData = $event->getPrimaryKeysWithPropertyChange(
            CategoryDefinition::ENTITY_NAME,
            ['parentId', 'afterCategoryId', 'visible', 'active']
        );
        if ($changedCategoryData !== []) {
            // if category data that has impact on navigation changes, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $deletedCategories = $event->getDeletedPrimaryKeys(CategoryDefinition::ENTITY_NAME);
        if ($deletedCategories !== []) {
            // if the category is deleted, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);

            return;
        }

        $changedCategoryTranslationData = $event->getPrimaryKeysWithPropertyChange(
            CategoryTranslationDefinition::ENTITY_NAME,
            ['name']
        );
        if ($changedCategoryTranslationData !== []) {
            // if translated category data that has impact on navigation changes, we invalidate the complete navigation route
            $this->cacheInvalidator->invalidate([NavigationRoute::ALL_TAG]);
        }
    }

    public function invalidatePaymentMethodRoute(EntityWrittenContainerEvent $event): void
    {
        // invalidates the payment method route when a payment method changed or an assignment between the sales channel and payment method changed
        $logs = [...$this->getChangedPaymentMethods($event), ...$this->getChangedPaymentAssignments($event)];

        $this->cacheInvalidator->invalidate($logs);
    }

    public function invalidateMedia(MediaIndexerEvent $event): void
    {
        /** @var list<array{'blog_id':string, 'variant_id':string|null}> $blogIds */
        $blogIds = $this->connection->fetchAllAssociative(
            'SELECT
                    LOWER(HEX(pm.blog_id)) as blog_id,
                    IF(variant.id IS NULL,  NULL, LOWER(HEX(variant.id))) as variant_id
                    FROM blog_media AS pm
                    LEFT JOIN blog as variant ON (pm.blog_id = variant.parent_id)
                    WHERE media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($event->getIds())],
            ['ids' => ArrayParameterType::STRING]
        );

        $variantIds = array_filter(array_column($blogIds, 'variant_id'));
        $uniqueBlogIds = array_unique(array_column($blogIds, 'blog_id'));
        $blogIds = array_merge(
            $uniqueBlogIds,
            $variantIds,
        );

        $tags = array_map(BlogDetailRoute::buildName(...), $blogIds);

        if (Feature::isActive('CACHE_REWORK')) {
            $tags = array_merge($tags, array_map(MediaRoute::buildName(...), $event->getIds()));
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function invalidateContext(EntityWrittenContainerEvent $event): void
    {
        // invalidates the context cache - each time one of the entities which are considered inside the context factory changed
        $ids = $event->getPrimaryKeys(ChannelDefinition::ENTITY_NAME);
        $keys = array_map(CachedChannelContextFactory::buildName(...), $ids);
        $keys = array_merge($keys, array_map(CachedBaseChannelContextFactory::buildName(...), $ids));

        if ($event->getEventByEntityName(CurrencyDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(PaymentMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(TaxDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(CountryDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(UserGroupDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        if ($event->getEventByEntityName(LanguageDefinition::ENTITY_NAME)) {
            $keys[] = CachedChannelContextFactory::ALL_TAG;
        }

        $keys = array_filter(array_unique($keys));

        if ($keys === []) {
            return;
        }

        // immediately invalidates the context cache
        $this->cacheInvalidator->invalidate($keys, true);
    }

    public function invalidateManufacturerFilters(EntityWrittenContainerEvent $event): void
    {
        // invalidates the blog listing route, each time a manufacturer changed
        $ids = $event->getPrimaryKeys(BlogManufacturerDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM blog_category_tree
                INNER JOIN blog ON blog.id = blog_category_tree.blog_id AND blog_category_tree.blog_version_id = blog.version_id
             WHERE blog.blog_manufacturer_id IN (:ids)
             AND blog.version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(BlogListingRoute::buildName(...), $ids)
        );
    }

    public function invalidatePropertyFilters(EntityWrittenContainerEvent $event): void
    {
        $this->cacheInvalidator->invalidate($this->getDeletedPropertyFilterTags($event));
    }

    public function invalidateStreamsBeforeIndexing(EntityWrittenContainerEvent $event): void
    {
        // invalidates all stream based pages and routes before the blog indexer changes content_stream_mapping
        $ids = $event->getPrimaryKeys(BlogDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        // invalidates blog listings which are based on a blog stream
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(content_stream_id))
             FROM content_stream_mapping
             WHERE content_stream_mapping.blog_id IN (:ids)
             AND content_stream_mapping.blog_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->cacheInvalidator->invalidate(
            array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids)
        );
    }

    /**
     * @return string[]
     */
    private function getDeletedPropertyFilterTags(EntityWrittenContainerEvent $event): array
    {
        // invalidates the blog listing route, each time a property changed
        $ids = $event->getDeletedPrimaryKeys(BlogPropertyDefinition::ENTITY_NAME);

        if ($ids === []) {
            return [];
        }

        $blogIds = array_column($ids, 'blogId');

        return array_merge(
            array_map(BlogDetailRoute::buildName(...), array_unique($blogIds)),
            array_map(BlogListingRoute::buildName(...), $this->getBlogCategoryIds($blogIds))
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getBlogCategoryIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) as category_id
             FROM blog_category_tree
             WHERE blog_id IN (:ids)
             AND blog_version_id = :version
             AND category_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(channel_id)) as id FROM channel_shipping_method WHERE shipping_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(ShippingMethodDefinition::ENTITY_NAME)) {
            $tags[] = ShippingMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(ShippingMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedShippingAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the shipping assignment of a sales channel
        $ids = $event->getPrimaryKeys(ChannelShippingMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'channelId');

        return array_map(ShippingMethodRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentMethods(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(channel_id)) as id FROM channel_payment_method WHERE payment_method_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(PaymentMethodDefinition::ENTITY_NAME)) {
            $tags[] = PaymentMethodRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(PaymentMethodRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedPaymentAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(ChannelPaymentMethodDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'channelId');

        return array_map(PaymentMethodRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedCountries(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CountryDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the country itself and invalidate the route for all sales channels in which the country is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(channel_id)) as id FROM channel_country WHERE country_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CountryDefinition::ENTITY_NAME)) {
            $tags[] = CountryRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CountryRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCountryAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the country assignment of a sales channel
        $ids = $event->getPrimaryKeys(ChannelCountryDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'channelId');

        return array_map(CountryRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedSalutations(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(SalutationDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        return [SalutationRoute::buildName()];
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguages(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(LanguageDefinition::ENTITY_NAME);
        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the language itself and invalidate the route for all sales channels in which the language is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(channel_id)) as id FROM channel_language WHERE language_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(LanguageDefinition::ENTITY_NAME)) {
            $tags[] = LanguageRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(LanguageRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedLanguageAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the language assignment of a sales channel
        $ids = $event->getPrimaryKeys(ChannelLanguageDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'channelId');

        return array_map(LanguageRoute::buildName(...), $ids);
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencies(EntityWrittenContainerEvent $event): array
    {
        $ids = $event->getPrimaryKeys(CurrencyDefinition::ENTITY_NAME);

        if ($ids === []) {
            return [];
        }

        // Used to detect changes to the currency itself and invalidate the route for all sales channels in which the currency is assigned.
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(channel_id)) as id FROM channel_currency WHERE currency_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $tags = [];
        if ($event->getDeletedPrimaryKeys(CurrencyDefinition::ENTITY_NAME)) {
            $tags[] = CurrencyRoute::ALL_TAG;
        }

        return array_merge($tags, array_map(CurrencyRoute::buildName(...), $ids));
    }

    /**
     * @return list<string>
     */
    private function getChangedCurrencyAssignments(EntityWrittenContainerEvent $event): array
    {
        // Used to detect changes to the currency assignment of a sales channel
        $ids = $event->getPrimaryKeys(ChannelCurrencyDefinition::ENTITY_NAME);

        $ids = array_column($ids, 'channelId');

        return array_map(CurrencyRoute::buildName(...), $ids);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(parent_id, id))) as id FROM blog WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getStreamIds(array $ids): array
    {
        if (!$this->contentStreamIndexerEnabled) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(content_stream_id))
             FROM content_stream_mapping
             WHERE content_stream_mapping.blog_id IN (:ids)
             AND content_stream_mapping.blog_version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return list<string>
     */
    private function getChangedCrossSellingBlogIds(EntityWrittenContainerEvent $event): array
    {
        /** @var list<string> $crossSellingIds */
        $crossSellingIds = array_values([
            ...$event->getPrimaryKeys(BlogCrossSellingDefinition::ENTITY_NAME),
            ...$this->getCrossSellingIdsFromAssignedBlogWrites($event->getEventByEntityName(BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME)),
            ...$this->getCrossSellingIdsByAssignedBlogIds($event->getPrimaryKeys(BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME)),
            ...$this->getCrossSellingIdsFromTranslationPrimaryKeys($event->getPrimaryKeys(BlogCrossSellingTranslationDefinition::ENTITY_NAME)),
        ]);

        return $this->getBlogIdsByCrossSellingIds($crossSellingIds);
    }

    /**
     * @return list<string>
     */
    private function getDeletedCrossSellingBlogIds(EntityDeleteEvent $event): array
    {
        /** @var list<string> $assignedBlogIds */
        $assignedBlogIds = array_values($event->getIds(BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME));

        /** @var list<string> $crossSellingIds */
        $crossSellingIds = array_values([
            ...$event->getIds(BlogCrossSellingDefinition::ENTITY_NAME),
            ...$this->getCrossSellingIdsByAssignedBlogIds($assignedBlogIds),
            ...$this->getCrossSellingIdsFromTranslationPrimaryKeys(array_values($event->getIds(BlogCrossSellingTranslationDefinition::ENTITY_NAME))),
        ]);

        return $this->getBlogIdsByCrossSellingIds($crossSellingIds);
    }

    /**
     * @param list<string|array<string, string>> $ids
     *
     * @return list<string>
     */
    private function getCrossSellingIdsFromTranslationPrimaryKeys(array $ids): array
    {
        $crossSellingIds = [];

        foreach ($ids as $id) {
            if (!\is_array($id)) {
                continue;
            }

            $crossSellingId = $id['blogCrossSellingId'] ?? $id['blog_cross_selling_id'] ?? null;

            if (!\is_string($crossSellingId)) {
                continue;
            }

            $crossSellingIds[] = Uuid::isValid($crossSellingId) ? $crossSellingId : Uuid::fromBytesToHex($crossSellingId);
        }

        return array_values(array_unique($crossSellingIds));
    }

    /**
     * @return list<string>
     */
    private function getCrossSellingIdsFromAssignedBlogWrites(?EntityWrittenEvent $event): array
    {
        if ($event === null) {
            return [];
        }

        $crossSellingIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $payloadId = $payload['crossSellingId'] ?? $payload['cross_selling_id'] ?? null;

            if (\is_string($payloadId)) {
                $crossSellingIds[] = Uuid::isValid($payloadId) ? $payloadId : Uuid::fromBytesToHex($payloadId);
            }

            $state = $writeResult->getExistence()?->getState() ?? [];
            $stateId = $state['crossSellingId'] ?? $state['cross_selling_id'] ?? null;

            if (\is_string($stateId)) {
                $crossSellingIds[] = Uuid::isValid($stateId) ? $stateId : Uuid::fromBytesToHex($stateId);
            }
        }

        return array_values(array_unique($crossSellingIds));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getCrossSellingIdsByAssignedBlogIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(cross_selling_id))
             FROM blog_cross_selling_assigned_products
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getBlogIdsByCrossSellingIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(blog.parent_id, blog.id)))
             FROM blog_cross_selling
             INNER JOIN blog
                ON blog.id = blog_cross_selling.blog_id
                AND blog.version_id = blog_cross_selling.blog_version_id
             WHERE blog_cross_selling.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getSetIds(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(snippet_set_id)) FROM snippet WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
