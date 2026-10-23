<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\FlowAction;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppFlowActionEntity>
 *
 * @codeCoverageIgnore
 */
class AppFlowActionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_flow_action_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppFlowActionEntity::class;
    }
}
