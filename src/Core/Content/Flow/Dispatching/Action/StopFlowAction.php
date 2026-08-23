<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;

/**
 * @internal
 */
class StopFlowAction extends FlowAction implements DelayableAction
{
    public static function getName(): string
    {
        return 'action.stop.flow';
    }

    /**
     * @return list<class-string>
     */
    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $flow->stop();
    }
}
