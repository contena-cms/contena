<?php declare(strict_types=1);

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Contena\Core\Content\Blog\Channel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AbstractListingProcessor;
use Contena\Core\Content\Flow\Dispatching\Action\FlowAction;
use Contena\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Contena\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Contena\Core\Content\Media\File\AbstractFileContentValidator;
use Contena\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Contena\Core\Content\Media\TypeDetector\TypeDetectorInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\Api\Sync\AbstractFkResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Increment\AbstractIncrementer;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Contena\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\Snippet\Filter\SnippetFilterInterface;
use Contena\Core\System\Tenant\Resolver\TenantResolverInterface;
use Contena\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Frontend\Framework\Captcha\AbstractCaptcha;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

return [
    'parameters' => [
        // Changing a mapped contract class is a backward compatibility break and must not be done in a minor release.
        'contenaTaggedServiceContractTagContracts' => [
            'flow.action' => FlowAction::class,
            'flow.storer' => FlowStorer::class,
            'messenger.receiver' => ReceiverInterface::class,
            'contena.api.enum_provider' => FieldEnumProviderInterface::class,
            'contena.dal.exception_handler' => ExceptionHandlerInterface::class,
            'contena.entity.definition' => EntityDefinition::class,
            'contena.entity.hookable' => [EntityDefinition::class, Entity::class],
            'contena.entity_indexer' => EntityIndexer::class,
            'contena.elastic.admin-searcher-index' => AbstractAdminIndexer::class,
            'contena.es.definition' => AbstractElasticsearchDefinition::class,
            'contena.filesystem.factory' => AdapterFactoryInterface::class,
            'contena.increment.gateway' => AbstractIncrementer::class,
            'contena.listing.filter.handler' => AbstractListingFilterHandler::class,
            'contena.listing.processor' => AbstractListingProcessor::class,
            'contena.media.file_content.validator' => AbstractFileContentValidator::class,
            'contena.media_type.detector' => TypeDetectorInterface::class,
            'contena.metadata.loader' => MetadataLoaderInterface::class,
            'contena.metric_transport_factory' => MetricTransportInterface::class,
            'contena.oauth.scope' => ScopeEntityInterface::class,
            'contena.path.strategy' => AbstractMediaPathStrategy::class,
            'contena.route_scope' => AbstractRouteScope::class,
            'contena.route_scope_whitelist' => RouteScopeWhitelistInterface::class,
            'contena.rule.condition' => Rule::class,
            'contena.scheduled.task' => ScheduledTask::class,
            'contena.seo_url.route' => SeoUrlRouteInterface::class,
            'contena.sitemap.config_handler' => ConfigHandlerInterface::class,
            'contena.sitemap_url_provider' => AbstractUrlProvider::class,
            'contena.snippet.filter' => SnippetFilterInterface::class,
            'contena.frontend.captcha' => AbstractCaptcha::class,
            'contena.tenant_resolver' => TenantResolverInterface::class,
            'contena.sync.fk_resolver' => AbstractFkResolver::class,
            'contena.system_check' => BaseCheck::class,
            'contena.telemetry.periodic_metric_collector' => PeriodicMetricCollectorInterface::class,
            'contena.twig.hierarchy_builder' => TemplateNamespaceHierarchyBuilderInterface::class,
            'contena.value_generator_connector' => AbstractIncrementStorage::class,
            'contena.value_generator_pattern' => AbstractValueGenerator::class,
            'frontend.media.upload.validator' => FrontendMediaValidatorInterface::class,
        ],
    ],
];
