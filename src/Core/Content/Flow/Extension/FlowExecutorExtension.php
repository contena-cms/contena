<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Extension;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;

/**
 * @public
 *
 * @title Flow Executor Extension
 *
 * @description This extension allows you to control the flow of execution or to pre-load and post-load specific data, enabling added monitoring capabilities or the ability to trigger external services.
 *
 * @extends Extension<void>
 *
 * @codeCoverageIgnore
 */
final class FlowExecutorExtension extends Extension
{
    public const NAME = 'flow.executor';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly Flow $flow,
        public readonly StorableFlow $event
    ) {
    }
}
