<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ContextRoute extends AbstractContextRoute
{
    public function getDecorated(): AbstractContextRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/context', name: 'channel-api.context', methods: ['GET'])]
    public function load(ChannelContext $context): ContextLoadRouteResponse
    {
        return new ContextLoadRouteResponse($context);
    }
}
