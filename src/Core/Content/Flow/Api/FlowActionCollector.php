<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Api;

use Contena\Core\Content\Flow\Dispatching\Action\FlowAction;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Events\FlowActionCollectorEvent;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlowActionCollector
{
    /**
     * @internal
     *
     * @param iterable<FlowAction> $actions
     * @param EntityRepository<AppFlowActionCollection> $appFlowActionRepo
     */
    public function __construct(
        private readonly iterable $actions,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $appFlowActionRepo,
    ) {
    }

    public function collect(Context $context): FlowActionCollectorResponse
    {
        $result = $this->fetchAppActions(new FlowActionCollectorResponse(), $context);
        foreach ($this->actions as $action) {
            if (!$action instanceof FlowAction) {
                continue;
            }

            $requirements = [];
            foreach ($action->requirements() as $class) {
                $requirements[] = lcfirst(new \ReflectionClass($class)->getShortName());
            }
            $result->set($action::getName(), new FlowActionDefinition($action::getName(), $requirements, $action instanceof DelayableAction));
        }

        $this->eventDispatcher->dispatch(new FlowActionCollectorEvent($result, $context));

        return $result;
    }

    private function fetchAppActions(FlowActionCollectorResponse $result, Context $context): FlowActionCollectorResponse
    {
        foreach ($this->appFlowActionRepo->search(new Criteria(), $context)->getEntities() as $action) {
            $requirements = [];
            foreach ($action->getRequirements() as $requirement) {
                $requirements[] = (string) $requirement;
            }
            $definition = new FlowActionDefinition($action->getName(), $requirements, $action->getDelayable());
            if (!$result->has($definition->getName())) {
                $result->set($definition->getName(), $definition);
            }
        }

        return $result;
    }
}
