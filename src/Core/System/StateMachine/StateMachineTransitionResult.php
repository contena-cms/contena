<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine;

use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
class StateMachineTransitionResult
{
    /**
     * @param bool $hasTransitioned - Whether the transition has been performed or not. This can be false if the transition was not necessary.
     */
    public function __construct(
        public readonly bool $hasTransitioned,
        public readonly StateMachineStateCollection $stateMachineStates,
        public readonly StateMachineEntity $stateMachine,
        public readonly StateMachineStateEntity $fromPlace,
        public readonly StateMachineStateEntity $toPlace,
    ) {
    }
}
