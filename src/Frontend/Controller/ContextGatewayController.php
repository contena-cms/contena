<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Gateway\Context\Channel\AbstractContextGatewayRoute;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put datas
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class ContextGatewayController extends FrontendController
{
    public function __construct(
        private readonly AbstractContextGatewayRoute $contextGatewayRoute,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/gateway/context', name: 'frontend.gateway.context', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function gateway(Request $request, ChannelContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        try {
            $response = $this->contextGatewayRoute->load($request, $cart, $context);
        } catch (GatewayException $e) {
            if ($e->getErrorCode() === GatewayException::CUSTOMER_MESSAGE) {
                $this->addFlash(self::DANGER, $e->getMessage());
            }

            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return $response;
    }
}
