<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractSitemapRoute
{
    abstract public function load(Request $request, ChannelContext $context): SitemapRouteResponse;

    abstract public function getDecorated(): AbstractSitemapRoute;
}
