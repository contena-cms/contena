<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Pagelet\Region\RegionDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class RegionController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(private readonly RegionDataPageletLoader $regionDataPageletLoader)
    {
    }

    #[Route(
        path: '/country/region-data',
        name: 'frontend.country.region.data',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function getRegionData(Request $request, ChannelContext $context): Response
    {
        $countryId = $request->query->getString('countryId');
        if (!$countryId) {
            throw RoutingException::missingRequestParameter('countryId');
        }

        $parentId = $request->query->get('parentId');
        $parentId = \is_string($parentId) && $parentId !== '' ? $parentId : null;
        $regionDataPagelet = $this->regionDataPageletLoader->load($countryId, $parentId, $request, $context);

        return new JsonResponse(['regions' => $regionDataPagelet->getRegions()]);
    }
}
