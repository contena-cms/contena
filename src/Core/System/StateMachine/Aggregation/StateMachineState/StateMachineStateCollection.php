<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineState;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineStateEntity>
 *
 * @codeCoverageIgnore
 */
class StateMachineStateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
