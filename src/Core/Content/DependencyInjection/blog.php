<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogSpecificationSource;
use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\Channel\ChannelBlogMainCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigExceptionHandler;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldExceptionHandler;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Api\BlogActionController;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogTypeRegistry;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AggregationListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AssociationLoadingListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\BehaviorListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompressedCriteriaListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\PagingListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\SortingListingProcessor;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingExceptionHandler;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingTranslationDefinition;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingLoaderConfigSerializer;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchLoaderConfigSerializer;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestLoaderConfigSerializer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogCategoryDenormalizer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserBuilder;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilder;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzer;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreter;
use Contena\Core\Content\Blog\SearchKeyword\KeywordLoader;
use Contena\Core\Content\Blog\Subscriber\BlogDescriptionTeaserSubscriber;
use Contena\Core\Content\Blog\Subscriber\CustomFieldSearchableSubscriber;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Contena\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(BlogDefinition::class)->tag('contena.entity.definition')->tag('contena.entity.hookable');
    $services->set(BlogActionController::class)
        ->public()
        ->args([service(BlogTypeRegistry::class)])
        ->call('setContainer', [service('service_container')]);
    $services->set(BlogTranslationDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogMediaDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogVisibilityDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogCategoryDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogCategoryTreeDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogTagDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogMainCategoryDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSearchKeywordDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogKeywordDictionaryDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSearchConfigDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSearchConfigFieldDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSortingDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSortingTranslationDefinition::class)->tag('contena.entity.definition');
    $services->set(BlogSortingExceptionHandler::class)->tag('contena.dal.exception_handler');
    $services->set(BlogSearchConfigExceptionHandler::class)->tag('contena.dal.exception_handler');
    $services->set(BlogSearchConfigFieldExceptionHandler::class)->tag('contena.dal.exception_handler');
    $services->set(ChannelBlogDefinition::class)->tag('contena.channel.entity.definition');
    $services->set(ChannelBlogMainCategoryDefinition::class)->tag('contena.channel.entity.definition');
    $services->set(BlogListRoute::class)->public()->args([service('channel.blog.repository')]);
    $services->set(BlogDetailRoute::class)->public()->args([service('channel.blog.repository'), service(CategoryBreadcrumbBuilder::class), service(CacheTagCollector::class)]);
    $services->set(CompositeListingProcessor::class)->args([tagged_iterator('contena.listing.processor')]);
    $services->set(CompressedCriteriaListingProcessor::class)
        ->args([service(CompressedCriteriaDecoder::class)])
        ->tag('contena.listing.processor', ['priority' => 1000]);
    $services->set(SortingListingProcessor::class)
        ->args([service(SystemConfigService::class), service('blog_sorting.repository')])
        ->tag('contena.listing.processor');
    $services->set(AggregationListingProcessor::class)
        ->args([tagged_iterator('contena.listing.filter.handler'), service('event_dispatcher')])
        ->tag('contena.listing.processor');
    $services->set(AssociationLoadingListingProcessor::class)->tag('contena.listing.processor');
    $services->set(BehaviorListingProcessor::class)->tag('contena.listing.processor', ['priority' => -1000]);
    $services->set(PagingListingProcessor::class)
        ->args([service(SystemConfigService::class)])
        ->tag('contena.listing.processor');
    $services->set(BlogListingRoute::class)->public()->args([service('channel.blog.repository'), service(CacheTagCollector::class), service('event_dispatcher'), service(CompositeListingProcessor::class)]);
    $services->set(BlogSearchRoute::class)->public()->args([service('channel.blog.repository'), service(BlogSearchBuilderInterface::class), service('event_dispatcher'), service(CompositeListingProcessor::class)]);
    $services->set(BlogSuggestRoute::class)->public()->args([service('channel.blog.repository'), service(BlogSearchBuilderInterface::class), service('event_dispatcher'), service(CompositeListingProcessor::class)]);
    $services->set(BlogSearchKeywordAnalyzer::class)->args([service(Tokenizer::class), service(TokenFilter::class), service(SearchConfigLoader::class)]);
    $services->set(KeywordLoader::class)->args([service(Connection::class)]);
    $services->set(BlogSearchTermInterpreter::class)->args([service(Tokenizer::class), service('logger'), service(TokenFilter::class), service(KeywordLoader::class), service(SearchConfigLoader::class)]);
    $services->set(BlogSearchBuilderInterface::class, BlogSearchBuilder::class)->args([service(BlogSearchTermInterpreter::class), service('logger'), param('contena.search.term_max_length'), param('contena.blog.search_keyword.indexing')]);
    $services->set(SearchKeywordUpdater::class)->args([service(Connection::class), service('language.repository'), service('blog.repository'), service(BlogSearchKeywordAnalyzer::class), service(ClockInterface::class), param('contena.blog.search_keyword.indexing')])->tag('kernel.reset', ['method' => 'reset']);
    $services->set(CustomFieldSearchableSubscriber::class)->args([service(Connection::class), service('parameter_bag')])->tag('kernel.event_subscriber');
    $services->set(BlogCategoryDenormalizer::class)->args([service(Connection::class)]);
    $services->set(BlogIndexer::class)->args([
        service(IteratorFactory::class),
        service('blog.repository'),
        service(Connection::class),
        service(BlogCategoryDenormalizer::class),
        service(ManyToManyIdFieldUpdater::class),
        service(SearchKeywordUpdater::class),
        service('event_dispatcher'),
        service(ClockInterface::class),
    ])->tag('contena.entity_indexer', ['priority' => 100]);
    $services->set(BlogDescriptionTeaserBuilder::class)->args([service(HtmlSanitizer::class)]);
    $services->set(BlogDescriptionTeaserSubscriber::class)->args([service(BlogDescriptionTeaserBuilder::class)])->tag('kernel.event_subscriber');
    $services->set(BlogDescriptionTeaserIndexer::class)->args([service(IteratorFactory::class), service(Connection::class), service(BlogDescriptionTeaserBuilder::class)])->tag('contena.entity_indexer');
    $services->set(BlogContentLayoutDefinition::class)->args([service(ContentLayoutMetadataDeriver::class)])->tag('contena.entity.definition');
    $services->set(BlogListingDataLoader::class)->args([service(BlogListingRoute::class)])->tag('content_system.data_loader');
    $services->set(BlogListingLoaderConfigSerializer::class)->tag('content_system.config_serializer');
    $services->set(BlogSearchDataLoader::class)->args([service(BlogSearchRoute::class)])->tag('content_system.data_loader');
    $services->set(BlogSearchLoaderConfigSerializer::class)->tag('content_system.config_serializer');
    $services->set(BlogSuggestDataLoader::class)->args([service(BlogSuggestRoute::class)])->tag('content_system.data_loader');
    $services->set(BlogSuggestLoaderConfigSerializer::class)->tag('content_system.config_serializer');
    $services->set(BlogSpecificationSource::class)->args([service('blog_content_layout.repository'), service(BlogContentLayoutDefinition::class), service(EntityLayoutContextFactory::class)])->tag('content_system.entity_specification_source', ['priority' => 100]);
    $services->set(BlogTypeRegistry::class)
        ->public()
        ->args([param('contena.blog.allowed_types')])
        ->tag('contena.api.enum_provider');
};
