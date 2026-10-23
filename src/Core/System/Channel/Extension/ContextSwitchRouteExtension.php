<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
final class ContextSwitchRouteExtension extends Extension
{
    public const NAME = 'context-switch-route.switch-context';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $data,
        public readonly ChannelContext $context,
    ) {
    }
}
