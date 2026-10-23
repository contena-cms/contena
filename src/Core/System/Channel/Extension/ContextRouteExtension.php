<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\Channel\ContextLoadRouteResponse;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextLoadRouteResponse>
 */
final class ContextRouteExtension extends Extension
{
    public const NAME = 'context-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ChannelContext $context,
    ) {
    }
}
