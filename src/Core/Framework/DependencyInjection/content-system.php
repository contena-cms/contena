<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Contena\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Contena\Core\Framework\ContentSystem\Api\ContentDiagnoseController;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutMutationController;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Contena\Core\Framework\ContentSystem\Api\UnknownRequestFieldExceptionListener;
use Contena\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\DefaultBindingSpecificationSynthesizer;
use Contena\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Contena\Core\Framework\ContentSystem\Binding\Registry\CachedContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Contena\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecificationValidator;
use Contena\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Contena\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\Channel\Routing\ContentRouteLoader;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Contena\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Contena\Core\Framework\ContentSystem\DraftLayoutChecker;
use Contena\Core\Framework\ContentSystem\Event\Listener\PostHydration\PartialRenderingExtractionSubscriber;
use Contena\Core\Framework\ContentSystem\Event\Listener\PostHydration\VirtualRootCleanupSubscriber;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\PartialRenderingPreparationSubscriber;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\PlaceholderResolutionSubscriber;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\RedistributeExpansionSubscriber;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\VirtualRootPreparationSubscriber;
use Contena\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Contena\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\CachedContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementListFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContextConsumersFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContextProvidersFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\DataRequirementsFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\ElementSlotsFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Field\ElementStyleFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\CachedContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Contena\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Contena\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Contena\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Contena\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Format\DecomposedResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\Format\SkeletonResponseFactory;
use Contena\Core\Framework\ContentSystem\Output\PartialRenderer;
use Contena\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Contena\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Contena\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Contena\Core\Framework\ContentSystem\Validation\ContentLayoutAssignmentWriteValidator;
use Contena\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Contena\Core\Framework\ContentSystem\Validation\LayoutGate;
use Contena\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Contena\Core\Framework\ContentSystem\Validation\LayoutTreeDecoder;
use Contena\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity Definitions
    $services->set(ContentLayoutDefinition::class)
        ->tag('contena.entity.definition');

    // Scaffolding Services
    $services->set(VirtualRootWrapper::class);

    // Output Services
    $services->set(PartialRenderer::class)
        ->args([
            service(ElementTreePruner::class),
            service(ContextDependencyAnalyzer::class),
            service(SubTreeExtractor::class),
        ]);

    // Event Listeners (Hydration Pipeline)
    // Pre-Hydration Listeners
    $services->set(VirtualRootPreparationSubscriber::class)
        ->args([
            service(VirtualRootWrapper::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(PlaceholderResolutionSubscriber::class)
        ->tag('kernel.event_listener');

    $services->set(RedistributeExpansionSubscriber::class)
        ->tag('kernel.event_listener');

    $services->set(PartialRenderingPreparationSubscriber::class)
        ->args([
            service(PartialRenderer::class),
        ])
        ->tag('kernel.event_listener');

    // Post-Hydration Listeners
    $services->set(VirtualRootCleanupSubscriber::class)
        ->args([
            service(VirtualRootWrapper::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(PartialRenderingExtractionSubscriber::class)
        ->args([
            service(PartialRenderer::class),
        ])
        ->tag('kernel.event_listener');

    // Field Serializers
    $services->set(DataRequirementsFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(DataLoaderConfigSerializerProvider::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ContextProvidersFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ContextConsumersFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ElementSlotsFieldSerializer::class)
        ->lazy()
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(ContentElementFieldSerializer::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ElementStyleFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(ContentSystemStyleOptionRegistry::class),
            service(StyleOptionConstraintDeriver::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ContentElementFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(DataRequirementsFieldSerializer::class),
            service(ContextProvidersFieldSerializer::class),
            service(ContextConsumersFieldSerializer::class),
            service(ElementSlotsFieldSerializer::class),
            service(ElementStyleFieldSerializer::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ContentElementListFieldSerializer::class)
        ->args([
            service(ValidatorInterface::class),
            service(DefinitionInstanceRegistry::class),
            service(ContentElementFieldSerializer::class),
            service(LayoutDefaultSeeder::class),
            service(AttributionReconciler::class),
        ])
        ->tag('contena.field_serializer');

    // Write-boundary default seeding (seeds type primitive defaults into every DAL write of the layout field)
    $services->set(PrimitiveDefaultProvider::class);

    $services->set(LayoutDefaultSeeder::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(PrimitiveDefaultProvider::class),
        ]);

    // Content Data Loaders
    $services->set(EntityLoader::class)
        ->args([
            service(ChannelDefinitionInstanceRegistry::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityCacheTagResolver::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(EntityCollectionLoader::class)
        ->args([
            service(ChannelDefinitionInstanceRegistry::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityCacheTagResolver::class),
        ])
        ->tag('content_system.data_loader');

    // Data Loader Provider with Tagged Locator
    $services->set(DataLoaderProvider::class)
        ->args([
            tagged_locator('content_system.data_loader', null, 'getRequirementType'),
        ]);

    // Config Serializers
    $services->set(EntityLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(EntityCollectionLoaderConfigSerializer::class)
        ->args([
            service(EntityLoaderConfigSerializer::class),
        ])
        ->tag('content_system.config_serializer');

    // Config Serializer Provider with Tagged Locator
    $services->set(DataLoaderConfigSerializerProvider::class)
        ->args([
            tagged_locator('content_system.config_serializer', null, 'getSource'),
        ]);

    // Config canonicalization for structural comparison (dedup hash, attribution reconciliation)
    $services->set(ConfigCanonicalizer::class);

    // Data Context Resolution
    $services->set(DataContextResolver::class)
        ->args([
            service(ContextPathResolver::class),
        ]);

    // Context Path Resolver
    $services->set(ContextPathResolver::class);

    // Cache Services
    $services->set(EntityCacheTagResolver::class);

    $services->set(CacheFinalizer::class)
        ->args([
            service(CacheTagCollector::class),
        ]);

    $services->set(CacheInvalidationSubscriber::class)
        ->args([
            service(CacheInvalidator::class),
            service(Connection::class),
            service(EntityCacheTagResolver::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_listener');

    // Hydration Services
    $services->set(ContentElementHydrator::class)
        ->args([
            service(DataLoaderProvider::class),
            service(DataContextResolver::class),
        ]);

    // Layout Context Utilities
    $services->set(ContextDependencyAnalyzer::class);

    // Output Services (Post-Hydration Processing)
    $services->set(ElementTreePruner::class);
    $services->set(SubTreeExtractor::class);

    // Entity Layout Services
    $services->set(EntityLayoutResolver::class);
    $services->set(EntityLayoutContextFactory::class)
        ->args([
            service(EntityLayoutResolver::class),
            service(RootContextMapper::class),
        ]);

    // Domain-Aware Layout Resolution (Header/Footer)
    $services->set(DomainAwareLayoutResolver::class);

    $services->set(NavigationAliasResolver::class);

    // Helper Services
    $services->set(ContentLayoutMetadataDeriver::class);

    // Content Pipeline
    $services->set(ContentPipeline::class)
        ->public()
        ->args([
            service(ContentElementHydrator::class),
            service('event_dispatcher'),
        ]);

    // Rendering Specification Factory
    $services->set(RenderingSpecificationFactory::class);

    // Section Resolvers
    $services->set(RenderingSpecificationResolver::class)
        ->args([
            tagged_iterator('content_system.entity_specification_source'),
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'main']);

    // Output Format Handlers
    $services->set(FullResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'full']);

    $services->set(SkeletonResponseFactory::class)
        ->tag('content_system.output_format', ['format' => 'skeleton']);

    $services->set(DataResponseFactory::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
            service(ConfigCanonicalizer::class),
        ])
        ->tag('content_system.output_format', ['format' => 'data']);

    $services->set(DecomposedResponseFactory::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
            service(ConfigCanonicalizer::class),
        ])
        ->tag('content_system.output_format', ['format' => 'decomposed']);

    // Schema Services
    $services->set(ContentSystemDataLoaderMapResolver::class)
        ->args([
            service(DataLoaderProvider::class),
        ]);

    $services->set(ContentSystemDataLoaderSchemaGenerator::class)
        ->args([
            service(ContentSystemDataLoaderMapResolver::class),
        ]);

    // Element Type System
    $services->set(ElementTypeSpecificationSerializer::class);

    $services->set(ElementTypeNameResolver::class);

    $services->set(ElementTypeCollisionDetector::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
        ]);

    $services->set(YamlTypeLoader::class)
        ->args([
            service(ElementTypeSpecificationSerializer::class),
            service('validator'),
            service(ElementTypeNameResolver::class),
        ])
        ->arg('$directories', [])
        ->tag('content_system.type_loader');

    $services->set(ContentSystemElementTypeRegistry::class)
        ->args([
            tagged_iterator('content_system.type_loader'),
        ]);

    $services->set(CachedContentSystemElementTypeRegistry::class)
        ->decorate(ContentSystemElementTypeRegistry::class)
        ->args([
            service(CachedContentSystemElementTypeRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    // Universal Style Option System
    $services->set(StyleOptionSpecificationSerializer::class);

    $services->set(StyleOptionCollisionDetector::class)
        ->args([
            service(ContentSystemStyleOptionRegistry::class),
        ]);

    $services->set(StyleOptionConstraintDeriver::class);

    $services->set(YamlStyleOptionLoader::class)
        ->args([
            service(StyleOptionSpecificationSerializer::class),
            service('validator'),
        ])
        ->arg('$directories', [])
        ->tag('content_system.style_option_loader');

    $services->set(ContentSystemStyleOptionRegistry::class)
        ->args([
            tagged_iterator('content_system.style_option_loader'),
        ]);

    $services->set(CachedContentSystemStyleOptionRegistry::class)
        ->decorate(ContentSystemStyleOptionRegistry::class)
        ->args([
            service(CachedContentSystemStyleOptionRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    // Binding Specification System
    $services->set(BindingSpecificationSerializer::class);

    // Load-time sugar canonicalizer: expands sugared resolves entries to canonical {loader, config} form before validation
    $services->set(BindingSpecificationCanonicalizer::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DefinitionInstanceRegistry::class),
            service(ChannelDefinitionInstanceRegistry::class),
        ]);

    // Synthesizes a type's default binding specification from its properties' resolvedBy keys
    $services->set(DefaultBindingSpecificationSynthesizer::class);

    $services->set(YamlBindingSpecificationLoader::class)
        ->arg('$directories', [])
        ->arg('$nameResolver', service(ElementTypeNameResolver::class))
        ->arg('$synthesizer', service(DefaultBindingSpecificationSynthesizer::class))
        ->arg('$serializer', service(BindingSpecificationSerializer::class))
        ->arg('$canonicalizer', service(BindingSpecificationCanonicalizer::class))
        ->arg('$validator', service('validator'))
        ->tag('content_system.binding_specification_loader');

    $services->set(ContentSystemBindingSpecificationRegistry::class)
        ->args([
            tagged_iterator('content_system.binding_specification_loader'),
        ]);

    $services->set(CachedContentSystemBindingSpecificationRegistry::class)
        ->decorate(ContentSystemBindingSpecificationRegistry::class)
        ->args([
            service(CachedContentSystemBindingSpecificationRegistry::class . '.inner'),
            service('cache.system'),
        ]);

    $services->set(TypeConsistentBindingSpecificationValidator::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(RootContextMapper::class),
            service(ContentSystemDataLoaderMapResolver::class),
        ])
        ->tag('validator.constraint_validator');

    // Write-seam attribution reconciliation: keeps a stored attributedSpecifications entry honest against current wiring
    $services->set(AttributionReconciler::class)
        ->args([
            service(ContentSystemBindingSpecificationRegistry::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(ConfigCanonicalizer::class),
        ]);

    // Apply-side of a binding decision, shared by the bind-element and insert-element mutation ops
    $services->set(BindingApplicator::class)
        ->args([
            service(DataLoaderConfigSerializerProvider::class),
        ]);

    // Root-source authority: the valid set of root sources (entity types + sections + none) and their resolution
    $services->set(NoneSpecificationSource::class);

    $services->set(RootSourceRegistry::class)
        ->arg('$entitySources', tagged_iterator('content_system.entity_specification_source'))
        ->arg('$sectionSources', tagged_locator('content_system.specification_source', 'section'))
        // $entityTypes set by ContentLayoutAssignableCompilerPass
        ->arg('$entityTypes', [])
        ->arg('$noneSource', service(NoneSpecificationSource::class));

    // Content Route Loader (arg 0 set by ContentRouteCompilerPass)
    $services->set(ContentRouteLoader::class)
        ->args([
            abstract_arg('route definitions, set by ContentRouteCompilerPass'),
        ])
        ->tag('routing.loader');

    // Resolution & Diagnostics
    $services->set(AvailableContextResolver::class)
        ->public()
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ElementResolver::class),
        ]);

    $services->set(ElementResolver::class)
        ->args([
            service(ContentSystemElementTypeRegistry::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DataLoaderConfigSerializerProvider::class),
            service(DataLoaderProvider::class),
        ]);

    $services->set(RootContextMapper::class)
        ->args([
            service(DataLoaderProvider::class),
        ]);

    $services->set(LayoutDiagnostics::class)
        ->args([
            service(AvailableContextResolver::class),
            service(ElementResolver::class),
            service(ContentSystemElementTypeRegistry::class),
            service(RootContextMapper::class),
            service(ContentSystemDataLoaderMapResolver::class),
            service(DataLoaderConfigSerializerProvider::class),
        ]);

    $services->set(LayoutGate::class)
        ->args([
            service(LayoutDiagnostics::class),
        ]);

    $services->set(ViolationConstraintMapper::class);

    $services->set(LayoutTreeDecoder::class)
        ->args([
            service(ContentLayoutDefinition::class),
            service(ContentElementListFieldSerializer::class),
        ]);

    // Shared read of a layout's immutable root source (in-flight write batch first, then committed row)
    $services->set(LayoutRootSourceReader::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    // Resolvability gate (DAL PreWriteValidationEvent)
    $services->set(ContentLayoutWriteValidator::class)
        ->args([
            service(LayoutGate::class),
            service(ViolationConstraintMapper::class),
            service(LayoutTreeDecoder::class),
            service(RootSourceRegistry::class),
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ContentLayoutAssignmentWriteValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');

    // Shared draft-layout decode (structural gate) for the preview, diagnose and mutation routes
    $services->set(DraftLayoutDecoder::class)
        ->args([
            service(ContentElementFieldSerializer::class),
        ]);

    // Remaps the serializer's ExtraAttributesException to a content-system 400 for the strict-mapped admin routes
    $services->set(UnknownRequestFieldExceptionListener::class)
        ->tag('kernel.event_subscriber');

    // Layout Validation
    $services->set(DraftLayoutChecker::class)
        ->args([
            service(LayoutDiagnostics::class),
        ]);

    // Resolve-and-diagnose Action (Admin API)
    $services->set(ContentDiagnoseController::class)
        ->public()
        ->args([
            service(DraftLayoutDecoder::class),
            service(LayoutDiagnostics::class),
            service(RootSourceRegistry::class),
        ]);

    $services->set(ContentPreviewPageBuilder::class)
        ->args([
            service(ChannelContextService::class),
            service(RenderingSpecificationResolver::class),
            service(DraftLayoutDecoder::class),
            service(DraftLayoutChecker::class),
            service(ContentPipeline::class),
        ]);

    $services->set(ContentPreviewPayloadStore::class)
        ->args([
            service('cache.system'),
        ]);

    // Preview Action (Admin API)
    $services->set(ContentPreviewController::class)
        ->public()
        ->args([
            service(ContentPreviewPageBuilder::class),
            service(FullResponseFactory::class),
            service(ContentPreviewPayloadStore::class),
        ]);

    // Mutation Pipeline
    $services->set(MutationPipeline::class)
        ->args([
            service(LayoutDiagnostics::class),
        ]);

    // Layout Mutation Actions (Admin API)
    $services->set(LayoutMutationController::class)
        ->public()
        ->args([
            service(DraftLayoutDecoder::class),
            service(MutationPipeline::class),
            service(ContentSystemElementTypeRegistry::class),
            service(RootSourceRegistry::class),
            service(ContentElementFieldSerializer::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service(BindingApplicator::class),
        ]);

    // Persisted Layout Mutation (load by id, mutate, commit through the gates)
    $services->set(PersistedLayoutMutator::class)
        ->args([
            service('lock.factory'),
            service('content_layout.repository'),
            service(ContentElementFieldSerializer::class),
            service(RootSourceRegistry::class),
            service(LayoutDiagnostics::class),
        ]);

    // Persisted Layout Mutation Actions (Admin API)
    $services->set(ContentLayoutMutationController::class)
        ->public()
        ->args([
            service(PersistedLayoutMutator::class),
            service(ContentSystemElementTypeRegistry::class),
            service(ContentElementFieldSerializer::class),
            service(DraftLayoutDecoder::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service(BindingApplicator::class),
        ]);
};
