<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ScheduledTaskEntity>
 */
class ScheduledTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_scheduled_task_collection';
    }

    protected function getExpectedClass(): string
    {
        return ScheduledTaskEntity::class;
    }
}
