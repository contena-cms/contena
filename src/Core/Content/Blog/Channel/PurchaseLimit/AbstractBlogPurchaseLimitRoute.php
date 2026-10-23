<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogPurchaseLimitRoute
{
    abstract public function getDecorated(): AbstractBlogPurchaseLimitRoute;

    abstract public function readBlogsPurchaseLimit(Request $request, ChannelContext $context): BlogPurchaseLimitRouteResponse;
}
