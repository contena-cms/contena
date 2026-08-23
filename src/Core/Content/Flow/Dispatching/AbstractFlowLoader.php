<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Framework\Context;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-type FlowHolder array{id: string, name: string, payload: Flow}
 * @phpstan-type EventGroupedFlowHolders array<string, array<FlowHolder>>
 */
abstract class AbstractFlowLoader
{
    /**
     * @return EventGroupedFlowHolders
     */
    abstract public function load(Context $context): array;
}
