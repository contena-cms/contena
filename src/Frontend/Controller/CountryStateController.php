<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CountryStateController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(private readonly CountryStateDataPageletLoader $countryStateDataPageletLoader)
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Remove POST request and use GET instead only
     */
    #[Route(
        path: '/country/country-state-data',
        name: 'frontend.country.country.data',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function getCountryData(Request $request, ChannelContext $context): Response
    {
        if ($request->isMethod(Request::METHOD_POST)) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The POST request to /country/country-state-data is deprecated and will be removed in v6.8.0.0. Use a GET request instead.'
            );
        }

        $countryId = $request->query->getString('countryId', $request->request->getString('countryId'));

        if (!$countryId) {
            throw RoutingException::missingRequestParameter('countryId');
        }

        $countryStateDataPagelet = $this->countryStateDataPageletLoader->load($countryId, $request, $context);

        $this->hook(new CountryStateDataPageletLoadedHook($countryStateDataPagelet, $context));

        return new JsonResponse([
            'states' => $countryStateDataPagelet->getStates(),
        ]);
    }
}
