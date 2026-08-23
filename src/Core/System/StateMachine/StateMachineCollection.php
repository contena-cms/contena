<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StateMachineEntity>
 */
class StateMachineCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'state_machine_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineEntity::class;
    }
}
