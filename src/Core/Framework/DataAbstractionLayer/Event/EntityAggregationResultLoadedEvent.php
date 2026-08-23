<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Event\NestedEvent;

class EntityAggregationResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    public function __construct(
        protected EntityDefinition $definition,
        protected AggregationResultCollection $result,
        protected Context $context
    ) {
        $this->name = $this->definition->getEntityName() . '.aggregation.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getResult(): AggregationResultCollection
    {
        return $this->result;
    }
}
