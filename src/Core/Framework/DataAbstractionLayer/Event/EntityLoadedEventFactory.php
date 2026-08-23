<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\Channel\Entity\PartialChannelEntityLoadedEvent;

/**
 * @internal
 */
class EntityLoadedEventFactory
{
    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    /**
     * @param array<mixed> $entities
     */
    public function create(array $entities, Context $context): EntityLoadedContainerEvent
    {
        $mapping = [];

        $this->recursion($entities, $mapping);

        $generator = static fn (EntityDefinition $definition, array $entities) => new EntityLoadedEvent($definition, $entities, $context);

        return $this->buildEvents($mapping, $generator, $context);
    }

    /**
     * @param array<mixed> $entities
     */
    public function createPartial(array $entities, Context $context): EntityLoadedContainerEvent
    {
        $mapping = [];

        $this->recursion($entities, $mapping);

        $generator = static fn (EntityDefinition $definition, array $entities) => new PartialEntityLoadedEvent($definition, $entities, $context);

        return $this->buildEvents($mapping, $generator, $context);
    }

    /**
     * @param array<mixed> $entities
     *
     * @return EntityLoadedContainerEvent[]
     */
    public function createForChannel(array $entities, ChannelContext $context): array
    {
        $mapping = [];

        $this->recursion($entities, $mapping);

        $generator = static fn (EntityDefinition $definition, array $entities) => new EntityLoadedEvent($definition, $entities, $context->getContext());
        $channelGenerator = static fn (EntityDefinition $definition, array $entities) => new ChannelEntityLoadedEvent($definition, $entities, $context);

        return [
            $this->buildEvents($mapping, $generator, $context->getContext()),
            $this->buildEvents($mapping, $channelGenerator, $context->getContext()),
        ];
    }

    /**
     * @param array<mixed> $entities
     *
     * @return EntityLoadedContainerEvent[]
     */
    public function createPartialForChannel(array $entities, ChannelContext $context): array
    {
        $mapping = [];

        $this->recursion($entities, $mapping);

        $generator = static fn (EntityDefinition $definition, array $entities) => new PartialEntityLoadedEvent($definition, $entities, $context->getContext());
        $channelGenerator = static fn (EntityDefinition $definition, array $entities) => new PartialChannelEntityLoadedEvent($definition, $entities, $context);

        return [
            $this->buildEvents($mapping, $generator, $context->getContext()),
            $this->buildEvents($mapping, $channelGenerator, $context->getContext()),
        ];
    }

    /**
     * @param array<string, list<Entity>> $mapping
     */
    private function buildEvents(array $mapping, \Closure $generator, Context $context): EntityLoadedContainerEvent
    {
        $events = [];
        foreach ($mapping as $name => $entities) {
            $definition = $this->registry->getByEntityName($name);

            $events[] = $generator($definition, $entities);
        }

        return new EntityLoadedContainerEvent($context, $events);
    }

    /**
     * @param array<mixed> $entities
     * @param array<string, list<Entity>> $mapping
     */
    private function recursion(array $entities, array &$mapping): void
    {
        foreach ($entities as $entity) {
            if (!$entity instanceof Entity && !$entity instanceof EntityCollection) {
                continue;
            }

            if ($entity instanceof EntityCollection) {
                $this->recursion($entity->getElements(), $mapping);
            } else {
                $this->map($entity, $mapping);
            }
        }
    }

    /**
     * @param array<string, list<Entity>> $mapping
     */
    private function map(Entity $entity, array &$mapping): void
    {
        $internalEntityName = $entity->getInternalEntityName() ?? '';
        $mapping[$internalEntityName][] = $entity;

        foreach ($entity->getVars() as $value) {
            if ($value instanceof Entity) {
                $this->map($value, $mapping);

                continue;
            }

            if ($value instanceof Collection) {
                $value = $value->getElements();
            }
            if (!\is_array($value)) {
                continue;
            }

            $this->recursion($value, $mapping);
        }
    }
}
