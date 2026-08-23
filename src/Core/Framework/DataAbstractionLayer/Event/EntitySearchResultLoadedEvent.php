<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\NestedEvent;

/**
 * @template TEntityCollection of EntityCollection
 */
class EntitySearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    /**
     * @param EntitySearchResult<TEntityCollection> $result
     */
    public function __construct(
        protected EntityDefinition $definition,
        protected EntitySearchResult $result
    ) {
        $this->name = $this->definition->getEntityName() . '.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    /**
     * @return EntitySearchResult<TEntityCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }
}
