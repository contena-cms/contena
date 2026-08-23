<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\NestedEventCollection;

/**
 * @template TEntity of Entity
 *
 * @implements \IteratorAggregate<array-key, TEntity>
 */
class EntityLoadedEvent extends NestedEvent implements GenericEvent, \IteratorAggregate
{
    protected string $name;

    /**
     * @param TEntity[] $entities
     */
    public function __construct(
        protected EntityDefinition $definition,
        protected array $entities,
        protected Context $context
    ) {
        $this->name = $this->definition->getEntityName() . '.loaded';
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entities);
    }

    /**
     * @return TEntity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        $ids = [];

        foreach ($this->entities as $entity) {
            $ids[] = $entity->getUniqueIdentifier();
        }

        return $ids;
    }
}
