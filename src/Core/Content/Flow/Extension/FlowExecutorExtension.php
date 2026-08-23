<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Extension;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Framework\Extensions\Extension;

/**
 * @public
 *
 * @extends Extension<void>
 *
 * @codeCoverageIgnore
 */
final class FlowExecutorExtension extends Extension
{
    final public const string NAME = 'flow.executor';

    /**
     * @internal
     */
    public function __construct(public readonly Flow $flow, public readonly StorableFlow $event)
    {
    }
}
