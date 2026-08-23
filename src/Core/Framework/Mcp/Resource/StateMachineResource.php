<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Json;
use Contena\Core\System\StateMachine\StateMachineCollection;

#[McpResource(
    uri: 'contena://state-machines',
    name: 'contena-state-machines',
    description: 'All generic workflow state machines with their states and transitions.'
)]
class StateMachineResource
{
    /**
     * @internal
     *
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     */
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('states');
        $criteria->addAssociation('transitions.fromStateMachineState');
        $criteria->addAssociation('transitions.toStateMachineState');

        $result = $this->stateMachineRepository->search($criteria, Context::createDefaultContext());

        $machines = [];
        foreach ($result->getEntities() as $machine) {
            $states = [];
            foreach ($machine->getStates() ?? [] as $state) {
                $states[] = [
                    'technicalName' => $state->getTechnicalName(),
                    'name' => $state->getName(),
                ];
            }

            $transitions = [];
            foreach ($machine->getTransitions() ?? [] as $transition) {
                $transitions[] = [
                    'actionName' => $transition->getActionName(),
                    'fromState' => $transition->getFromStateMachineState()?->getTechnicalName(),
                    'toState' => $transition->getToStateMachineState()?->getTechnicalName(),
                ];
            }

            $machines[] = [
                'technicalName' => $machine->getTechnicalName(),
                'name' => $machine->getName(),
                'states' => $states,
                'transitions' => $transitions,
            ];
        }

        return [
            'uri' => 'contena://state-machines',
            'mimeType' => 'application/json',
            'text' => Json::encode($machines),
        ];
    }
}
