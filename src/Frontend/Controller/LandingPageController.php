<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class LandingPageController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LandingPageLoader $landingPageLoader,
        private readonly AbstractContentRoute $contentRoute,
    ) {
    }

    #[Route(path: '/landingPage/{landingPageId}', name: LandingPageSeoUrlRoute::ROUTE_NAME, defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function index(ChannelContext $context, Request $request): Response
    {
        $page = $this->landingPageLoader->load($request, $context);
        $landingPage = $page->getLandingPage();
        \assert($landingPage !== null);

        $response = $this->contentRoute->load('/landing-page/' . $landingPage->getId(), $request, $context);
        \assert($response instanceof ContentRouteResponse);

        return $this->renderFrontend('@Frontend/frontend/page/landing-page/index.html.twig', [
            'page' => $page,
            'contentPage' => $response->getContentPage(),
            'isNewContentStructure' => true,
        ]);
    }
}
