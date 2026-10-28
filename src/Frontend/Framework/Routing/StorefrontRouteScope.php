<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Routing\SalesChannelContextRouteScopeDependant;
use Contena\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class StorefrontRouteScope extends AbstractRouteScope implements SalesChannelContextRouteScopeDependant
{
    final public const ID = 'storefront';

    public function isAllowed(Request $request): bool
    {
        return $request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            && $request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST) === true
        ;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
