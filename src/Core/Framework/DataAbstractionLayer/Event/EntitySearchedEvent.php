<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class EntitySearchedEvent extends Event implements ContenaEvent
{
    public function __construct(
        private readonly Criteria $criteria,
        private readonly EntityDefinition $definition,
        private readonly Context $context
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
