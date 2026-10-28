<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
abstract class AbstractMediaRoute
{
    abstract public function getDecorated(): AbstractMediaRoute;

    abstract public function load(Request $request, SalesChannelContext $context): MediaRouteResponse;
}
