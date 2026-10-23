<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBreadcrumbRoute
{
    abstract public function getDecorated(): AbstractBreadcrumbRoute;

    abstract public function load(Request $request, ChannelContext $channelContext): BreadcrumbRouteResponse;
}
