<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
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
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true, referencedType: 'list<string>'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $entityName = $inputs->string('entity');

        if (!$this->definitionRegistry->has($entityName)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityIds = $inputs->stringListOrNull('property');

        if ($entityIds === null || $entityIds === []) {
            return $this->emptyCollectionResult($entityName);
        }

        $entityIds = \array_map(static fn (string $entityId) => u($entityId)->lower()->toString(), $entityIds);

        // An unsubstituted placeholder left literal in the stored list passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() in EntityDefinitionQueryHelper::addIdCondition(). One bad entry degrades the
        // whole element rather than being filtered out: a malformed string means broken authoring, and a
        // silently shortened collection would hide it.
        foreach ($entityIds as $entityId) {
            if (!Uuid::isValid($entityId)) {
                return ContentDataLoaderResult::notFound();
            }
        }

        // Any ContenaHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // The set is fully open here: this loader searches an arbitrary registered entity.
        try {
            $entities = $this->loadEntities($entityName, $entityIds, $inputs->stringList('associations'), $context);

            // The has() check above only proves the entity name is in the registry's map
            // (DefinitionInstanceRegistry::has() is an isset on it); getByEntityName() still throws
            // DefinitionNotFoundException when the mapped definition service is absent from the container, so
            // it sits inside the catch rather than after it.
            $definition = $this->definitionRegistry->getByEntityName($entityName);
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }

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

    /**
     * Degrades rather than propagating: resolveDefinition() throws DefinitionNotFoundException when the
     * mapped definition service is absent from the container. resolveDefinition() itself stays throwing,
     * because resolveProducedType() is an introspection path that must fail hard on an unknown entity.
     */
    private function emptyCollectionResult(string $entityName): ContentDataLoaderResult
    {
        try {
            /** @var class-string<EntityCollection<Entity>> $collectionClass */
            $collectionClass = $this->resolveDefinition($entityName)->getCollectionClass();
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cached(new $collectionClass());
    }

    /**
     * The sales-channel definition for the entity where one exists, otherwise the base definition.
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
            $criteria->addAssociation($association);
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
