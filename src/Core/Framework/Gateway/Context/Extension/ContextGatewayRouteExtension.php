<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
final class ContextGatewayRouteExtension extends Extension
{
    public const NAME = 'context-gateway-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly ChannelContext $context,
    ) {
    }
}
