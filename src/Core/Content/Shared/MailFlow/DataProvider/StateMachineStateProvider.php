<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<StateMachineStateEntity, StateMachineStateCollection>
 */
class StateMachineStateProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return StateMachineStateDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('stateMachine');

        return $criteria;
    }
}
