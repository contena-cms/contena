<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StateMachineTransitionEntity>
 */
class StateMachineTransitionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_transition_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineTransitionEntity::class;
    }
}
