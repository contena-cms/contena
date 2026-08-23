<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCookieRoute
{
    abstract public function getDecorated(): AbstractCookieRoute;

    abstract public function getCookieGroups(Request $request, ChannelContext $channelContext): CookieRouteResponse;
}
