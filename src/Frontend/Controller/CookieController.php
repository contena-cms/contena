<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Cookie\Channel\AbstractCookieConsentLogRoute;
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
        private readonly AbstractCookieConsentLogRoute $cookieConsentLogRoute,
    ) {
    }

    #[Route(path: '/cookie/offcanvas', name: 'frontend.cookie.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: [Request::METHOD_GET])]
    public function offcanvas(Request $request, ChannelContext $channelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $channelContext);
        $response = $this->renderFrontend('@Frontend/frontend/layout/cookie/cookie-configuration.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/permission', name: 'frontend.cookie.permission', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: [Request::METHOD_GET])]
    public function permission(Request $request, ChannelContext $channelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $channelContext);
        $response = $this->renderFrontend('@Frontend/frontend/layout/cookie/cookie-permission.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/groups', name: 'frontend.cookie.groups', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: [Request::METHOD_GET])]
    public function groups(Request $request, ChannelContext $channelContext): JsonResponse
    {
        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($request, $channelContext);

        return $this->json($cookieRouteResponse->getObject());
    }

    /**
     * Called via navigator.sendBeacon, which cannot send custom headers,
     * so this route must not require an XMLHttpRequest header.
     */
    #[Route(path: '/cookie/consent-log', name: 'frontend.cookie.consent.log', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: [Request::METHOD_POST])]
    public function logConsent(Request $request, ChannelContext $channelContext): Response
    {
        return $this->cookieConsentLogRoute->log($request, $channelContext);
    }

    private function getCookieGroupsFromCookieRoute(Request $request, ChannelContext $channelContext): CookieGroupCollection
    {
        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($request, $channelContext);

        return $cookieRouteResponse->getCookieGroups();
    }
}
