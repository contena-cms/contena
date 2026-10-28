<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Contena\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Content\Product\AbstractIsNewDetector;
use Contena\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Contena\Core\Content\Product\AbstractPropertyGroupSorter;
use Contena\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Contena\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingExceptionHandler;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Contena\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Contena\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Contena\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Contena\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Contena\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Contena\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigExceptionHandler;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldExceptionHandler;
use Contena\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Contena\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Contena\Core\Content\Product\Api\ProductActionController;
use Contena\Core\Content\Product\Api\ProductNumberFkResolver;
use Contena\Core\Content\Product\Cart\ProductGateway;
use Contena\Core\Content\Product\Cart\ProductLineItemCommandValidator;
use Contena\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTask;
use Contena\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler;
use Contena\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask;
use Contena\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Contena\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Contena\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Contena\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Contena\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Contena\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Contena\Core\Content\Product\Cms\ProductNameCmsElementResolver;
use Contena\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor;
use Contena\Core\Content\Product\Cms\ProductSlider\StaticProductProcessor;
use Contena\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Contena\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Contena\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Contena\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Contena\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Contena\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Contena\Core\Content\Product\Garan\GaranLabelInlineImage;
use Contena\Core\Content\Product\Garan\GaranLabelMailSubscriber;
use Contena\Core\Content\Product\Garan\GaranLabelOrderSubscriber;
use Contena\Core\Content\Product\Garan\GaranLabelProductValidator;
use Contena\Core\Content\Product\Garan\GaranLabelRenderer;
use Contena\Core\Content\Product\Garan\GaranLabelResolver;
use Contena\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Contena\Core\Content\Product\IsNewDetector;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductMaxPurchaseCalculator;
use Contena\Core\Content\Product\ProductTypeRegistry;
use Contena\Core\Content\Product\ProductVariationBuilder;
use Contena\Core\Content\Product\PropertyGroupSorter;
use Contena\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Contena\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Contena\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Contena\Core\Content\Product\SalesChannel\Garan\AbstractGaranLabelRoute;
use Contena\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\AggregationListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\AssociationLoadingListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\BehaviorListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\CompressedCriteriaListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Contena\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Contena\Core\Content\Product\SalesChannel\Price\AppScriptProductPriceCalculator;
use Contena\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Contena\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Contena\Core\Content\Product\SalesChannel\ProductListRoute;
use Contena\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Contena\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Contena\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Contena\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Contena\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Contena\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingExceptionHandler;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Contena\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Contena\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Contena\Core\Content\Product\Stock\AvailableStockMirrorSubscriber;
use Contena\Core\Content\Product\Stock\LoadProductStockSubscriber;
use Contena\Core\Content\Product\Stock\OrderStockSubscriber;
use Contena\Core\Content\Product\Stock\StockStorage;
use Contena\Core\Content\Product\Subscriber\CustomFieldSearchableSubscriber;
use Contena\Core\Content\Product\Subscriber\ProductDescriptionTeaserSubscriber;
use Contena\Core\Content\Product\Subscriber\ProductSubscriber;
use Contena\Core\Content\Product\Subscriber\RepairDigitalProductStatesSubscriber;
use Contena\Core\Content\Product\Util\VariantCombinationLoader;
use Contena\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(AbstractListingFilterHandler::class)
        ->tag('contena.listing.filter.handler');

    $services->set(ProductExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductSortingExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(ProductStreamMappingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelProductDefinition::class)
        ->tag('contena.sales_channel.entity.definition');

    $services->set(ProductCategoryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductCustomFieldSetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductConfiguratorSettingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductConfiguratorSettingExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductPriceDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(ProductPropertyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSearchKeywordDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductKeywordDictionaryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductReviewDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductManufacturerDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductManufacturerTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductMediaDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductDownloadDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductOptionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductCategoryTreeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductCrossSellingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductCrossSellingTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductCrossSellingAssignedProductsDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductFeatureSetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductFeatureSetTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSortingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSortingTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSearchConfigDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSearchConfigFieldDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductSearchConfigFieldExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductSearchConfigExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductGateway::class)
        ->args([
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(AbstractPropertyGroupSorter::class, PropertyGroupSorter::class);

    $services->set(AbstractProductMaxPurchaseCalculator::class, ProductMaxPurchaseCalculator::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(AbstractIsNewDetector::class, IsNewDetector::class)
        ->args([
            service(SystemConfigService::class),
            service(ClockInterface::class),
        ]);

    $services->set(ProductVariationBuilder::class);

    $services->set(GaranLabelDurationFormatter::class);

    $services->set(GaranLabelRenderer::class)
        ->args([
            service('twig'),
        ]);

    $services->set(GaranLabelResolver::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service(GaranLabelRenderer::class),
        ]);

    $services->set(GaranLabelTwigFilter::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service('product.repository'),
            service(GaranLabelResolver::class),
            service(GaranLabelInlineImage::class),
        ])
        ->tag('twig.extension');

    $services->set(GaranLabelInlineImage::class);

    $services->set(GaranLabelMailSubscriber::class)
        ->args([
            service(GaranLabelInlineImage::class),
            service('product.repository'),
            service(GaranLabelResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(GaranLabelOrderSubscriber::class)
        ->args([
            service('product.repository'),
            service(GaranLabelResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(GaranLabelProductValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldSearchableSubscriber::class)
        ->args([
            service(Connection::class),
            service('parameter_bag'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RepairDigitalProductStatesSubscriber::class)
        ->args([
            service(Connection::class),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(AbstractKeyValueStorage::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ProductSubscriber::class)
        ->args([
            service(ProductVariationBuilder::class),
            service(ProductPriceCalculator::class),
            service(AbstractPropertyGroupSorter::class),
            service(AbstractProductMaxPurchaseCalculator::class),
            service(AbstractIsNewDetector::class),
            service(SystemConfigService::class),
            service(ProductMeasurementUnitBuilder::class),
            service(MeasurementUnitConverter::class),
            service('request_stack'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserBuilder::class)
        ->args([
            service(HtmlSanitizer::class),
        ]);

    $services->set(ProductDescriptionTeaserSubscriber::class)
        ->args([
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service(Connection::class),
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(OrderStockSubscriber::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            param('contena.stock.enable_stock_management'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AvailableStockMirrorSubscriber::class)
        ->tag('kernel.event_listener');

    $services->set(LoadProductStockSubscriber::class)
        ->args([
            service(StockStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductSearchKeywordAnalyzer::class)
        ->args([
            service(Tokenizer::class),
            service(TokenFilter::class),
            service(SearchConfigLoader::class),
        ]);

    $services->set(ProductActionController::class)
        ->public()
        ->args([
            service(VariantCombinationLoader::class),
            service(ProductTypeRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ProductVisibilityDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(VariantCombinationLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(DeliveryTimeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ProductBoxCmsElementResolver::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(ProductListingCmsElementResolver::class)
        ->args([
            service(ProductListingRoute::class),
            service('product_sorting.repository'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(ProductSliderCmsElementResolver::class)
        ->args([
            tagged_iterator('contena.cms.product_slider.processor'),
            service('logger'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(StaticProductProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.cms.product_slider.processor');

    $services->set(ProductStreamProcessor::class)
        ->args([
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
            service('logger'),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
        ])
        ->tag('contena.cms.product_slider.processor');

    $services->set(ProductNameCmsElementResolver::class)
        ->tag('contena.cms.data_resolver');

    $services->set(ManufacturerLogoCmsElementResolver::class)
        ->tag('contena.cms.data_resolver');

    $services->set(CrossSellingCmsElementResolver::class)
        ->args([
            service(ProductCrossSellingRoute::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(ProductDescriptionReviewsCmsElementResolver::class)
        ->args([
            service(ProductReviewLoader::class),
            service(ScriptExecutor::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(ProductPriceCalculator::class)
        ->args([
            service('unit.repository'),
            service(QuantityPriceCalculator::class),
            service(ExtensionDispatcher::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppScriptProductPriceCalculator::class)
        ->decorate(ProductPriceCalculator::class)
        ->args([
            service(AppScriptProductPriceCalculator::class . '.inner'),
            service(ScriptExecutor::class),
            service(ScriptPriceStubs::class),
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
            service('product_sorting.repository'),
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

    $services->set(ProductSearchBuilderInterface::class, ProductSearchBuilder::class)
        ->args([
            service(ProductSearchTermInterpreter::class),
            service('logger'),
            param('contena.search.term_max_length'),
            param('contena.product.search_keyword.indexing'),
        ]);

    $services->set(ProductLineItemCommandValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResolvedCriteriaProductSuggestRoute::class)
        ->decorate(ProductSuggestRoute::class, null, -2000)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service('event_dispatcher'),
            service(ResolvedCriteriaProductSuggestRoute::class . '.inner'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ProductSuggestRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductSearchRoute::class)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service(ProductListingLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ResolvedCriteriaProductSearchRoute::class)
        ->decorate(ProductSearchRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolvedCriteriaProductSearchRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ResolveCriteriaProductListingRoute::class)
        ->decorate(ProductListingRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolveCriteriaProductListingRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(FindProductVariantRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(CacheTagCollector::class),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    // decorated by cached route
    $services->set(ProductListingRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
            service('category.repository'),
            service(ProductStreamBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
            service('logger'),
        ]);

    $services->set(ProductIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('product.repository'),
            service(Connection::class),
            service(VariantListingUpdater::class),
            service(ProductCategoryDenormalizer::class),
            service(InheritanceUpdater::class),
            service(RatingAverageUpdater::class),
            service(SearchKeywordUpdater::class),
            service(ChildCountUpdater::class),
            service(ManyToManyIdFieldUpdater::class),
            service(StockStorage::class),
            service('event_dispatcher'),
            service(CheapestPriceUpdater::class),
            service(ProductStreamUpdater::class),
            service('messenger.default_bus'),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(ClockInterface::class),
        ])
        ->tag('contena.entity_indexer', ['priority' => 100]);

    $services->set(ProductStreamUpdater::class)
        ->args([
            service(Connection::class),
            service(ProductDefinition::class),
            service('product.repository'),
            service('messenger.default_bus'),
            service(ManyToManyIdFieldUpdater::class),
            service('language.repository'),
            param('contena.product_stream.indexing'),
        ])
        ->tag('contena.entity_indexer');

    $services->set(ProductTypeRegistry::class)
        ->public()
        ->args([
            param('contena.product.allowed_types'),
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

    $services->set(ProductCategoryDenormalizer::class)
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
            service('product.repository'),
            service(ProductSearchKeywordAnalyzer::class),
            service(ClockInterface::class),
            param('contena.product.search_keyword.indexing'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(StockUpdateFilterProvider::class)
        ->args([
            tagged_iterator('contena.product.stock_filter'),
        ]);

    $services->set(StockStorage::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductListingLoader::class)
        ->args([
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service('event_dispatcher'),
            service(ProductCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductDetailRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service('product_translation.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ProductConfiguratorLoader::class),
            service(CategoryBreadcrumbBuilder::class),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelProductDefinition::class),
            service(ProductCloseoutFilterFactory::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductPurchaseLimitRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(AbstractProductMaxPurchaseCalculator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductReviewLoader::class)
        ->args([
            service(ProductReviewRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductReviewRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(SystemConfigService::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
            param('contena.api.store.max_limit'),
            service(CompressedCriteriaDecoder::class),
        ]);

    $services->set(ProductConfiguratorLoader::class)
        ->args([
            service(AvailableCombinationLoader::class),
            service('property_group_option.repository'),
        ]);

    $services->set(AvailableCombinationLoader::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            service(SystemConfigService::class),
        ]);

    $services->set(ProductCrossSellingRoute::class)
        ->public()
        ->args([
            service('product_cross_selling.repository'),
            service('event_dispatcher'),
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(ProductListingLoader::class),
            service(ProductCloseoutFilterFactory::class),
            service(CacheTagCollector::class),
            service(Connection::class),
            service(ExtensionDispatcher::class),
            service('logger'),
        ]);

    $services->set(ProductReviewSaveRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ProductProvider::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductListRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(GaranLabelRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(GaranLabelResolver::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->alias(AbstractGaranLabelRoute::class, GaranLabelRoute::class);

    $services->set(BuyBoxCmsElementResolver::class)
        ->args([
            service(ProductConfiguratorLoader::class),
            service('product_review.repository'),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(TokenFilter::class)
        ->args([
            service(SearchConfigLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CheapestPriceAccessorBuilder::class)
        ->args([
            param('contena.dal.max_rule_prices'),
            service('logger'),
        ])
        ->tag('contena.field_accessor_builder', ['priority' => -200]);

    $services->set(CleanupProductKeywordDictionaryTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupProductKeywordDictionaryTaskHandler::class)
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

    $services->set(ProductCloseoutFilterFactory::class);

    $services->set(ProductNumberFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
