<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class BlogController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogPageLoader $blogPageLoader,
        private readonly AbstractContentRoute $contentRoute,
    ) {
    }

    #[Route(path: '/blog/{blogId}', name: BlogPageSeoUrlRoute::ROUTE_NAME, options: ['seo' => true], defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function detail(Request $request, ChannelContext $context): Response
    {
        $page = $this->blogPageLoader->load($request, $context);
        $response = $this->contentRoute->load('/blog/' . $page->getBlog()->getId(), $request, $context);
        \assert($response instanceof ContentRouteResponse);

        return $this->renderFrontend('@Frontend/frontend/page/blog/detail.html.twig', [
            'page' => $page,
            'contentPage' => $response->getContentPage(),
            'isNewContentStructure' => true,
        ]);
    }
}
