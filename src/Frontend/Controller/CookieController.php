<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Cookie\Channel\AbstractCookieRoute;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the cookie-configuration.html.twig template including all cookies returned by the "getCookieGroup"-method
 *
 * Cookies are returned within groups, groups require the "group" attribute
 * A group is structured as described above the "getCookieGroup"-method
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CookieController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
    ) {
    }

    #[Route(path: '/cookie/offcanvas', name: 'frontend.cookie.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function offcanvas(Request $request, ChannelContext $channelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $channelContext);
        $response = $this->renderFrontend('@Frontend/frontend/layout/cookie/cookie-configuration.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/permission', name: 'frontend.cookie.permission', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function permission(Request $request, ChannelContext $channelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $channelContext);
        $response = $this->renderFrontend('@Frontend/frontend/layout/cookie/cookie-permission.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/consent-offcanvas', name: 'frontend.cookie.consent.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function cookieConsentOffcanvas(Request $request, ChannelContext $context): Response
    {
        $featureName = $request->query->get('featureName', 'wishlist');
        $cookieName = $request->query->get('cookieName', 'wishlist-enabled');

        return $this->renderFrontend('@Frontend/frontend/layout/cookie/cookie-consent-offcanvas.html.twig', [
            'featureName' => $featureName,
            'cookieName' => $cookieName,
        ]);
    }

    #[Route(path: '/cookie/groups', name: 'frontend.cookie.groups', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function groups(Request $request, ChannelContext $channelContext): JsonResponse
    {
        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($request, $channelContext);

        return $this->json($cookieRouteResponse->getObject());
    }

    private function getCookieGroupsFromCookieRoute(Request $request, ChannelContext $channelContext): CookieGroupCollection
    {
        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($request, $channelContext);

        return $cookieRouteResponse->getCookieGroups();
    }
}
