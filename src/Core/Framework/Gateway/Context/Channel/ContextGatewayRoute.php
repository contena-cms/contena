<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Channel;

use Contena\Core\Framework\App\Context\Gateway\AppContextGateway;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ContextGatewayRoute extends AbstractContextGatewayRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppContextGateway $contextGateway,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractContextGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }


}
