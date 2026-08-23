<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\NestedEvent;

class EntityIdSearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    public function __construct(
        protected EntityDefinition $definition,
        protected IdSearchResult $result
    ) {
        $this->name = $this->definition->getEntityName() . '.id.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
