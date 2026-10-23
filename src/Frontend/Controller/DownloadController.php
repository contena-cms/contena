<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\Channel\AbstractDownloadRoute;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class DownloadController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDownloadRoute $downloadRoute)
    {
    }

    #[Route(path: '/account/order/download/{orderId}/{downloadId}', name: 'frontend.account.order.single.download', methods: ['GET'])]
    public function downloadFile(Request $request, ChannelContext $context): Response
    {
        if (!$context->getUser()) {
            return $this->redirectToRoute(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => $request->query->get('deepLinkCode') ?? false,
                ]
            );
        }

        return $this->downloadRoute->load($request, $context);
    }
}
