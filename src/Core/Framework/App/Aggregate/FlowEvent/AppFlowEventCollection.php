<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\FlowEvent;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppFlowEventEntity>
 *
 * @codeCoverageIgnore
 */
class AppFlowEventCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_flow_event_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppFlowEventEntity::class;
    }
}
