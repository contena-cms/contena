<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractMediaRoute
{
    abstract public function getDecorated(): AbstractMediaRoute;

    abstract public function load(Request $request, ChannelContext $context): MediaRouteResponse;
}
