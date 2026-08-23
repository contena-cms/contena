<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Events;

use Contena\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;

class FlowActionCollectorEvent extends NestedEvent
{
    public function __construct(
        private readonly FlowActionCollectorResponse $flowActions,
        private readonly Context $context,
    ) {
    }

    public function getCollection(): FlowActionCollectorResponse
    {
        return $this->flowActions;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
