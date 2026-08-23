<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Routing\ChannelContextRouteScopeDependant;
use Symfony\Component\HttpFoundation\Request;

class FrontendRouteScope extends AbstractRouteScope implements ChannelContextRouteScopeDependant
{
    final public const ID = 'frontend';

    public function isAllowed(Request $request): bool
    {
        return $request->attributes->has(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)
            && $request->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST) === true
        ;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
