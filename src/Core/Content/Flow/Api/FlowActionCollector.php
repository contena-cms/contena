<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Api;

use Contena\Core\Content\Flow\Dispatching\Action\FlowAction;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Events\FlowActionCollectorEvent;
use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlowActionCollector
{
    /**
     * @internal
     *
     * @param iterable<FlowAction> $actions
     */
    public function __construct(
        private readonly iterable $actions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function collect(Context $context): FlowActionCollectorResponse
    {
        $result = new FlowActionCollectorResponse();
        foreach ($this->actions as $action) {
            $requirements = array_values(array_map(
                static fn (string $class): string => lcfirst(new \ReflectionClass($class)->getShortName()),
                $action->requirements()
            ));
            $result->set($action::getName(), new FlowActionDefinition($action::getName(), $requirements, $action instanceof DelayableAction));
        }

        $this->eventDispatcher->dispatch(new FlowActionCollectorEvent($result, $context));

        return $result;
    }
}
