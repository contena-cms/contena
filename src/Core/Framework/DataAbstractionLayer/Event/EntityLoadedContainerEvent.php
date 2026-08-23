<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\NestedEventCollection;

class EntityLoadedContainerEvent extends NestedEvent
{
    public function __construct(
        private readonly Context $context,
        private readonly array $events
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection($this->events);
    }
}
