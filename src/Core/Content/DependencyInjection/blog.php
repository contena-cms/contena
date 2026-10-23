<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Content\Blog\AbstractIsNewDetector;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCustomFieldSet\BlogCustomFieldSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigExceptionHandler;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldExceptionHandler;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\Aggregate\ContentStreamMapping\ContentStreamMappingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Api\BlogActionController;
use Contena\Core\Content\Blog\Api\BlogNumberFkResolver;
use Contena\Core\Content\Blog\Cleanup\CleanupBlogKeywordDictionaryTask;
use Contena\Core\Content\Blog\Cleanup\CleanupBlogKeywordDictionaryTaskHandler;
use Contena\Core\Content\Blog\Cleanup\CleanupUnusedDownloadMediaTask;
use Contena\Core\Content\Blog\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Contena\Core\Content\Blog\Cms\BuyBoxCmsElementResolver;
use Contena\Core\Content\Blog\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Blog\Cms\ManufacturerLogoCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogBoxCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogDescriptionReviewsCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogListingCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogNameCmsElementResolver;
use Contena\Core\Content\Blog\Cms\BlogSlider\ContentStreamProcessor;
use Contena\Core\Content\Blog\Cms\BlogSlider\StaticBlogProcessor;
use Contena\Core\Content\Blog\Cms\BlogSliderCmsElementResolver;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogCategoryDenormalizer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserBuilder;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogExceptionHandler;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\RatingAverageUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingUpdater;
use Contena\Core\Content\Blog\IsNewDetector;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogTypeRegistry;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\Detail\AvailableCombinationLoader;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\Garan\AbstractGaranLabelRoute;
use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRoute;
use Contena\Core\Content\Blog\Channel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ManufacturerListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PriceListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PropertyListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\RatingListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ShippingFreeListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\ResolveCriteriaBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Price\AppScriptBlogPriceCalculator;
use Contena\Core\Content\Blog\Channel\Price\BlogPriceCalculator;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewSaveRoute;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\ResolvedCriteriaBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingExceptionHandler;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingTranslationDefinition;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\ResolvedCriteriaBlogSuggestRoute;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilder;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzer;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreter;
use Contena\Core\Content\Blog\Subscriber\CustomFieldSearchableSubscriber;
use Contena\Core\Content\Blog\Subscriber\BlogDescriptionTeaserSubscriber;
use Contena\Core\Content\Blog\Subscriber\BlogSubscriber;
use Contena\Core\Content\Blog\Subscriber\RepairDigitalBlogStatesSubscriber;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AggregationListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AssociationLoadingListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\BehaviorListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompressedCriteriaListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\PagingListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\SortingListingProcessor;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(AbstractListingFilterHandler::class)
        ->tag('contena.listing.filter.handler');

    $services->set(BlogExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(BlogSortingExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(BlogDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(ContentStreamMappingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelBlogDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(BlogCategoryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogCustomFieldSetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogTagDefinition::class)
        ->tag('contena.entity.definition');





    $services->set(BlogSearchKeywordDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogKeywordDictionaryDefinition::class)
        ->tag('contena.entity.definition');




    $services->set(BlogMediaDefinition::class)
        ->tag('contena.entity.definition');


    $services->set(BlogTranslationDefinition::class)
        ->tag('contena.entity.definition');


    $services->set(BlogCategoryTreeDefinition::class)
        ->tag('contena.entity.definition');






    $services->set(BlogSortingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogSortingTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogSearchConfigDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogSearchConfigFieldDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogSearchConfigFieldExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(BlogSearchConfigExceptionHandler::class)
        ->tag('contena.dal.exception_handler');




    $services->set(AbstractIsNewDetector::class, IsNewDetector::class)
        ->args([
            service(SystemConfigService::class),
            service(ClockInterface::class),
        ]);










    $services->set(CustomFieldSearchableSubscriber::class)
        ->args([
            service(Connection::class),
            service('parameter_bag'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RepairDigitalBlogStatesSubscriber::class)
        ->args([
            service(Connection::class),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(AbstractKeyValueStorage::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(BlogSubscriber::class)
        ->args([
            service(BlogPriceCalculator::class),
            service(AbstractIsNewDetector::class),
            service(SystemConfigService::class),
            service('request_stack'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlogDescriptionTeaserBuilder::class)
        ->args([
            service(HtmlSanitizer::class),
        ]);

    $services->set(BlogDescriptionTeaserSubscriber::class)
        ->args([
            service(BlogDescriptionTeaserBuilder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlogDescriptionTeaserIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service(Connection::class),
            service(BlogDescriptionTeaserBuilder::class),
        ])
        ->tag('contena.entity_indexer');




    $services->set(BlogSearchKeywordAnalyzer::class)
        ->args([
            service(Tokenizer::class),
            service(TokenFilter::class),
            service(SearchConfigLoader::class),
        ]);

    $services->set(BlogActionController::class)
        ->public()
        ->args([
            service(BlogTypeRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(BlogVisibilityDefinition::class)
        ->tag('contena.entity.definition');



    $services->set(BlogBoxCmsElementResolver::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(BlogListingCmsElementResolver::class)
        ->args([
            service(BlogListingRoute::class),
            service('blog_sorting.repository'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(BlogSliderCmsElementResolver::class)
        ->args([
            tagged_iterator('contena.cms.blog_slider.processor'),
            service('logger'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(StaticBlogProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.cms.blog_slider.processor');

    $services->set(ContentStreamProcessor::class)
        ->args([
            service(ContentStreamBuilder::class),
            service('channel.blog.repository'),
            service('event_dispatcher'),
            service('logger'),
            service(SystemConfigService::class),
            service(BlogCloseoutFilterFactory::class),
        ])
        ->tag('contena.cms.blog_slider.processor');

    $services->set(BlogNameCmsElementResolver::class)
        ->tag('contena.cms.data_resolver');

    $services->set(ManufacturerLogoCmsElementResolver::class)
        ->tag('contena.cms.data_resolver');

    $services->set(CrossSellingCmsElementResolver::class)
        ->args([
            service(BlogCrossSellingRoute::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(BlogDescriptionReviewsCmsElementResolver::class)
        ->args([
            service(BlogReviewLoader::class),
            service(ScriptExecutor::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(BlogPriceCalculator::class)
        ->args([
            service('unit.repository'),
            service(ExtensionDispatcher::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppScriptBlogPriceCalculator::class)
        ->decorate(BlogPriceCalculator::class)
        ->args([
            service(AppScriptBlogPriceCalculator::class . '.inner'),
            service(ScriptExecutor::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CompositeListingProcessor::class)
        ->args([
            tagged_iterator('contena.listing.processor'),
        ]);

    $services->set(CompressedCriteriaListingProcessor::class)
        ->args([
            service(CompressedCriteriaDecoder::class),
        ])
        // priority needs to be higher than of all other processors to make custom filters passed in compressed criteria to work
        ->tag('contena.listing.processor', ['priority' => 1000]);

    $services->set(ManufacturerListingFilterHandler::class);

    $services->set(PriceListingFilterHandler::class);

    $services->set(RatingListingFilterHandler::class);

    $services->set(ShippingFreeListingFilterHandler::class);

    $services->set(PropertyListingFilterHandler::class)
        ->args([
            service('property_group.repository'),
            service('property_group_option.repository'),
            service(Connection::class),
        ]);

    $services->set(SortingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('blog_sorting.repository'),
            service('event_dispatcher'),
        ])
        ->tag('contena.listing.processor');

    $services->set(AggregationListingProcessor::class)
        ->args([
            tagged_iterator('contena.listing.filter.handler'),
            service('event_dispatcher'),
        ])
        ->tag('contena.listing.processor');

    $services->set(AssociationLoadingListingProcessor::class)
        ->tag('contena.listing.processor');

    $services->set(BehaviorListingProcessor::class)
        ->tag('contena.listing.processor', ['priority' => -1000]);

    $services->set(PagingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            param('contena.api.store.max_limit'),
        ])
        ->tag('contena.listing.processor');

    $services->set(BlogSearchBuilderInterface::class, BlogSearchBuilder::class)
        ->args([
            service(BlogSearchTermInterpreter::class),
            service('logger'),
            param('contena.search.term_max_length'),
            param('contena.blog.search_keyword.indexing'),
        ]);


    $services->set(ResolvedCriteriaBlogSuggestRoute::class)
        ->decorate(BlogSuggestRoute::class, null, -2000)
        ->public()
        ->args([
            service(BlogSearchBuilderInterface::class),
            service('event_dispatcher'),
            service(ResolvedCriteriaBlogSuggestRoute::class . '.inner'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(BlogSuggestRoute::class)
        ->public()
        ->args([
            service(BlogListingLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(BlogSearchRoute::class)
        ->public()
        ->args([
            service(BlogSearchBuilderInterface::class),
            service(BlogListingLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ResolvedCriteriaBlogSearchRoute::class)
        ->decorate(BlogSearchRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolvedCriteriaBlogSearchRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ResolveCriteriaBlogListingRoute::class)
        ->decorate(BlogListingRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolveCriteriaBlogListingRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(FindBlogVariantRoute::class)
        ->public()
        ->args([
            service('channel.blog.repository'),
            service(CacheTagCollector::class),
            service(SystemConfigService::class),
            service(BlogCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    // decorated by cached route
    $services->set(BlogListingRoute::class)
        ->public()
        ->args([
            service(BlogListingLoader::class),
            service('category.repository'),
            service(ContentStreamBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
            service('logger'),
        ]);

    $services->set(BlogIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('blog.repository'),
            service(Connection::class),
            service(VariantListingUpdater::class),
            service(BlogCategoryDenormalizer::class),
            service(InheritanceUpdater::class),
            service(RatingAverageUpdater::class),
            service(SearchKeywordUpdater::class),
            service(ChildCountUpdater::class),
            service(ManyToManyIdFieldUpdater::class),
            service('event_dispatcher'),
            service(CheapestPriceUpdater::class),
            service(ContentStreamUpdater::class),
            service('messenger.default_bus'),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(ClockInterface::class),
        ])
        ->tag('contena.entity_indexer', ['priority' => 100]);

    $services->set(ContentStreamUpdater::class)
        ->args([
            service(Connection::class),
            service(BlogDefinition::class),
            service('blog.repository'),
            service('messenger.default_bus'),
            service(ManyToManyIdFieldUpdater::class),
            service('language.repository'),
            param('contena.content_stream.indexing'),
        ])
        ->tag('contena.entity_indexer');

    $services->set(BlogTypeRegistry::class)
        ->public()
        ->args([
            param('contena.blog.allowed_types'),
        ])
        ->tag('contena.api.enum_provider');

    $services->set(StatesUpdater::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(VariantListingUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(BlogCategoryDenormalizer::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CheapestPriceUpdater::class)
        ->args([
            service(Connection::class),
            service(CheapestPriceQuantitySelector::class),
            service('event_dispatcher'),
        ]);

    $services->set(CheapestPriceQuantitySelector::class);

    $services->set(RatingAverageUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SearchKeywordUpdater::class)
        ->args([
            service(Connection::class),
            service('language.repository'),
            service('blog.repository'),
            service(BlogSearchKeywordAnalyzer::class),
            service(ClockInterface::class),
            param('contena.blog.search_keyword.indexing'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(StockUpdateFilterProvider::class)
        ->args([
            tagged_iterator('contena.blog.stock_filter'),
        ]);


    $services->set(BlogListingLoader::class)
        ->args([
            service('channel.blog.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service('event_dispatcher'),
            service(BlogCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(BlogDetailRoute::class)
        ->public()
        ->args([
            service('channel.blog.repository'),
            service('blog_translation.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(BlogConfiguratorLoader::class),
            service(CategoryBreadcrumbBuilder::class),
            service(ChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(ChannelBlogDefinition::class),
            service(BlogCloseoutFilterFactory::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(BlogPurchaseLimitRoute::class)
        ->public()
        ->args([
            service('channel.blog.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(BlogReviewLoader::class)
        ->args([
            service(BlogReviewRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(BlogReviewRoute::class)
        ->public()
        ->args([
            service('blog_review.repository'),
            service(SystemConfigService::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
            param('contena.api.store.max_limit'),
            service(CompressedCriteriaDecoder::class),
        ]);

    $services->set(BlogConfiguratorLoader::class)
        ->args([
            service(AvailableCombinationLoader::class),
            service('property_group_option.repository'),
        ]);

    $services->set(AvailableCombinationLoader::class)
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ]);

    $services->set(BlogCrossSellingRoute::class)
        ->public()
        ->args([
            service('blog_cross_selling.repository'),
            service('event_dispatcher'),
            service(ContentStreamBuilder::class),
            service('channel.blog.repository'),
            service(SystemConfigService::class),
            service(BlogListingLoader::class),
            service(BlogCloseoutFilterFactory::class),
            service(CacheTagCollector::class),
            service(Connection::class),
            service(ExtensionDispatcher::class),
            service('logger'),
        ]);

    $services->set(BlogReviewSaveRoute::class)
        ->public()
        ->args([
            service('blog_review.repository'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(BlogProvider::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(BlogListRoute::class)
        ->public()
        ->args([
            service('channel.blog.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(GaranLabelRoute::class)
        ->public()
        ->args([
            service('channel.blog.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->alias(AbstractGaranLabelRoute::class, GaranLabelRoute::class);

    $services->set(BuyBoxCmsElementResolver::class)
        ->args([
            service(BlogConfiguratorLoader::class),
            service('blog_review.repository'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(TokenFilter::class)
        ->args([
            service(SearchConfigLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);


    $services->set(CleanupBlogKeywordDictionaryTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupBlogKeywordDictionaryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupUnusedDownloadMediaTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupUnusedDownloadMediaTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(UnusedMediaPurger::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(BlogCloseoutFilterFactory::class);

    $services->set(BlogNumberFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
