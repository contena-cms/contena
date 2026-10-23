<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ContextTokenResponse;
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

}
