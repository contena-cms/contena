<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Channel;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\App\Context\Gateway\AppContextGateway;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Contena\Core\Framework\Gateway\Context\Extension\ContextGatewayRouteExtension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
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

    #[Route(path: '/store-api/context/gateway', name: 'store-api.context.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, ChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ContextGatewayRouteExtension::NAME,
            extension: new ContextGatewayRouteExtension($request, $cart, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, Cart $cart, ChannelContext $context): ContextTokenResponse
    {
        return $this->contextGateway->process(new ContextGatewayPayloadStruct($cart, $context, new RequestDataBag($request->request->all())));
    }
}
