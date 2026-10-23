<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Extension;

use Contena\Core\Content\Cookie\Channel\CookieRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CookieRouteResponse>
 */
final class CookieRouteExtension extends Extension
{
    public const NAME = 'cookie-route.get-cookie-groups';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $channelContext,
    ) {
    }
}
