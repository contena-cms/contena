<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<Entity>
 */
class EntityLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'entity';

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

            if ($definition->getEntityClass() === ArrayEntity::class) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $producedType = isset($channelDefinitions[$entityName])
                ? $channelDefinitions[$entityName]->getEntityClass()
                : $definition->getEntityClass();

            $capabilities[] = new LoaderTypeCapability($producedType, ['entity' => $entityName]);
        }

        return $capabilities;
    }

    public function resolveProducedType(AbstractContentDataLoaderConfig $config): string
    {
        if (!$config instanceof EntityLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, $config::class);
        }

        if ($this->channelDefinitionRegistry->has($config->entity)) {
            return $this->channelDefinitionRegistry->getByEntityName($config->entity)->getEntityClass();
        }

        try {
            return $this->definitionRegistry->getByEntityName($config->entity)->getEntityClass();
        } catch (DefinitionNotFoundException) {
            throw ContentSystemException::unknownLoaderEntity($config->entity);
        }
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

        $propertyName = $config->property ?? $config->entity;
        $entityId = $element->getProperty($propertyName);

        if (!\is_string($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityId = u($entityId)->lower()->toString();
        $entity = $this->loadEntity($config->entity, $entityId, $config->associations, $context);

        if ($entity === null) {
            return ContentDataLoaderResult::notFound();
        }

        $definition = $this->definitionRegistry->getByEntityName($config->entity);
        $cacheTag = $this->cacheTagResolver->resolve($definition, $entityId);

        if ($cacheTag === null) {
            return ContentDataLoaderResult::uncacheable($entity);
        }

        return ContentDataLoaderResult::cached($entity, $cacheTag);
    }

    /**
     * @param list<string> $associations
     */
    private function loadEntity(
        string $entityName,
        string $entityId,
        array $associations,
        ChannelContext $context
    ): ChannelEntity|Entity|null {
        $criteria = new Criteria([$entityId]);

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

        return $result->getEntities()->first();
    }
}
