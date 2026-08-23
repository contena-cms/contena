<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;

class BusinessEventCollectorEvent extends NestedEvent
{
    final public const string NAME = 'collect.business-events';

    public function __construct(
        private readonly BusinessEventCollectorResponse $events,
        private readonly Context $context,
    ) {
    }

    public function getCollection(): BusinessEventCollectorResponse
    {
        return $this->events;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
