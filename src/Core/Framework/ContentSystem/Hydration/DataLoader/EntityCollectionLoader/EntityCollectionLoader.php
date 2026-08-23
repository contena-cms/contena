<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<EntityCollection<Entity>>
 */
class EntityCollectionLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'entity_collection';

    public function __construct(
        private readonly ChannelDefinitionInstanceRegistry $channelDefinitionRegistry,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityCacheTagResolver $cacheTagResolver,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function producibleTypes(): array
    {
        $channelDefinitions = $this->channelDefinitionRegistry->getChannelDefinitions();

        $capabilities = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if ($definition instanceof MappingEntityDefinition) {
                continue;
            }

            if ($definition->getCollectionClass() === EntityCollection::class) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $producedDefinition = $channelDefinitions[$entityName] ?? $definition;

            /** @var class-string<EntityCollection<Entity>> $collectionClass */
            $collectionClass = $producedDefinition->getCollectionClass();

            $capabilities[] = new LoaderTypeCapability(
                $collectionClass,
                ['entity' => $entityName],
                [$producedDefinition->getEntityClass()],
            );
        }

        return $capabilities;
    }

    public function resolveProducedType(AbstractContentDataLoaderConfig $config): string
    {
        if (!$config instanceof EntityLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, $config::class);
        }

        /** @var class-string<EntityCollection<Entity>> $collectionClass */
        $collectionClass = $this->resolveDefinition($config->entity)->getCollectionClass();

        return $collectionClass;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof EntityLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        if (!$this->definitionRegistry->has($config->entity)) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? $config->entity . 'Ids';
        $entityIds = $element->getProperty($propertyName);

        if ($entityIds === null) {
            return $this->emptyCollectionResult($config->entity);
        }

        if (!\is_array($entityIds)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityIds = \array_filter($entityIds, static fn ($id) => \is_string($id));
        $entityIds = \array_map(static fn ($entityId) => u($entityId)->lower()->toString(), $entityIds);
        $entityIds = \array_values($entityIds);

        if ($entityIds === []) {
            return $this->emptyCollectionResult($config->entity);
        }

        $entities = $this->loadEntities($config->entity, $entityIds, $config->associations, $context);

        $definition = $this->definitionRegistry->getByEntityName($config->entity);
        $tags = [];

        foreach ($entities as $entity) {
            $tag = $this->cacheTagResolver->resolve($definition, $entity->getUniqueIdentifier());

            if ($tag === null) {
                return ContentDataLoaderResult::uncacheable($entities);
            }

            $tags[] = $tag;
        }

        return ContentDataLoaderResult::cached($entities, ...$tags);
    }

    private function emptyCollectionResult(string $entityName): ContentDataLoaderResult
    {
        /** @var class-string<EntityCollection<Entity>> $collectionClass */
        $collectionClass = $this->resolveDefinition($entityName)->getCollectionClass();

        return ContentDataLoaderResult::cached(new $collectionClass());
    }

    /**
     * The Channel definition for the entity where one exists, otherwise the base definition.
     */
    private function resolveDefinition(string $entityName): EntityDefinition
    {
        if ($this->channelDefinitionRegistry->has($entityName)) {
            return $this->channelDefinitionRegistry->getByEntityName($entityName);
        }

        try {
            return $this->definitionRegistry->getByEntityName($entityName);
        } catch (DefinitionNotFoundException) {
            throw ContentSystemException::unknownLoaderEntity($entityName);
        }
    }

    /**
     * @param list<string> $entityIds
     * @param list<string> $associations
     *
     * @return EntityCollection<covariant Entity>
     */
    private function loadEntities(
        string $entityName,
        array $entityIds,
        array $associations,
        ChannelContext $context
    ): EntityCollection {
        $criteria = new Criteria($entityIds);

        foreach ($associations as $association) {
            if (\is_string($association)) {
                $criteria->addAssociation($association);
            }
        }

        try {
            $channelRepository = $this->channelDefinitionRegistry->getChannelRepository($entityName);
            $result = $channelRepository->search($criteria, $context);
        } catch (ChannelRepositoryNotFoundException) {
            $repository = $this->definitionRegistry->getRepository($entityName);
            $result = $repository->search($criteria, $context->getContext());
        }

        return $result->getEntities();
    }
}
