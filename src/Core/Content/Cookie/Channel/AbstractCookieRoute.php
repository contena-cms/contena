<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
abstract class AbstractCookieRoute
{
    abstract public function getDecorated(): AbstractCookieRoute;

    abstract public function getCookieGroups(Request $request, ChannelContext $channelContext): CookieRouteResponse;
}
