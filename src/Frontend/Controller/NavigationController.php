<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Page\Navigation\NavigationPage;
use Contena\Frontend\Page\Navigation\NavigationPageLoaderInterface;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class NavigationController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationPageLoaderInterface $navigationPageLoader,
        private readonly MenuOffcanvasPageletLoaderInterface $offcanvasLoader,
        private readonly HeaderPageletLoaderInterface $headerLoader,
        private readonly FooterPageletLoaderInterface $footerLoader,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly AbstractContentRoute $contentRoute,
        private readonly AbstractContentRoute $headerContentRoute,
        private readonly AbstractContentRoute $footerContentRoute,
    ) {
    }

    #[Route(path: '/', name: 'frontend.home.page', options: ['seo' => true], defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function home(Request $request, ChannelContext $context): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderFrontend(
            '@Frontend/frontend/page/content/page.html.twig',
            [
                'page' => $page,
                'contentPage' => $this->loadCategoryContentPage($page, $request, $context),
                'isNewContentStructure' => true,
            ]
        );
    }

    #[Route(path: '/navigation/{navigationId}', name: NavigationPageSeoUrlRoute::ROUTE_NAME, options: ['seo' => true], defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function index(ChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);
        $category = $page->getCategory();
        \assert($category !== null);

        if ($category->getType() === CategoryDefinition::TYPE_LINK) {
            $host = (string) $request->attributes->get(RequestTransformer::FRONTEND_URL);
            $urlPlaceholder = $this->categoryUrlGenerator->generate($category, $context->getChannel());

            if (!$urlPlaceholder) {
                throw CategoryException::categoryNotFound($category->getId());
            }

            return new RedirectResponse($this->seoUrlReplacer->replace($urlPlaceholder, $host, $context));
        }

        return $this->renderFrontend(
            '@Frontend/frontend/page/content/page.html.twig',
            [
                'page' => $page,
                'contentPage' => $this->loadCategoryContentPage($page, $request, $context),
                'isNewContentStructure' => true,
            ]
        );
    }

    #[Route(path: '/content/{path}', name: 'frontend.content.layout', options: ['seo' => false], defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], requirements: ['path' => '.+'], methods: [Request::METHOD_GET])]
    public function content(string $path, Request $request, ChannelContext $context): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);
        $contentPage = $this->loadRequiredContentPage($path, $request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/content/raw.html.twig', [
            'page' => $page,
            'contentPage' => $contentPage,
            'isNewContentStructure' => true,
        ]);
    }

    #[Route(
        path: '/widgets/menu/offcanvas',
        name: 'frontend.menu.offcanvas',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function offcanvas(Request $request, ChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        $response = $this->renderFrontend(
            '@Frontend/frontend/layout/navigation/offcanvas/navigation-pagelet.html.twig',
            ['page' => $page],
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/_esi/global/header',
        name: 'frontend.header',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            '_esi' => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function header(Request $request, ChannelContext $context): Response
    {
        $header = $this->headerLoader->load($request, $context);

        $headerParameters = $request->query->all('headerParameters');
        $contentPage = $this->loadContentPage($this->headerContentRoute, '', $request, $context);

        if ($contentPage !== null || \array_key_exists('isNewContentStructure', $headerParameters)) {
            return $this->renderFrontend('@Frontend/frontend/page/content/header.html.twig', [
                'header' => $header,
                'contentPage' => $contentPage,
                'headerParameters' => $headerParameters,
                'isNewContentStructure' => true,
            ]);
        }

        return $this->renderFrontend('@Frontend/frontend/layout/header.html.twig', [
            'header' => $header,
            'headerParameters' => $headerParameters,
        ]);
    }

    #[Route(
        path: '/_esi/global/footer',
        name: 'frontend.footer',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            '_esi' => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function footer(Request $request, ChannelContext $context): Response
    {
        $footer = $this->footerLoader->load($request, $context);
        $contentPage = $this->loadContentPage($this->footerContentRoute, '', $request, $context);

        return $this->renderFrontend('@Frontend/frontend/layout/footer.html.twig', [
            'footer' => $footer,
            'contentPage' => $contentPage,
            'footerParameters' => $request->query->all()['footerParameters'] ?? [],
        ]);
    }

    private function loadCategoryContentPage(NavigationPage $page, Request $request, ChannelContext $context): ContentPage
    {
        $category = $page->getCategory();
        \assert($category !== null);

        $categoryId = $category->getId();
        $path = '/category/' . $categoryId;

        return $this->loadRequiredContentPage($path, $request, $context);
    }

    private function loadRequiredContentPage(string $path, Request $request, ChannelContext $context): ContentPage
    {
        $response = $this->contentRoute->load($path, $request, $context);
        \assert($response instanceof ContentRouteResponse);

        return $response->getContentPage();
    }

    private function loadContentPage(
        AbstractContentRoute $contentRoute,
        string $path,
        Request $request,
        ChannelContext $context,
    ): ?ContentPage {
        try {
            $contentPageResponse = $contentRoute->load($path, $request, $context);

            if (!$contentPageResponse instanceof ContentRouteResponse) {
                return null;
            }

            $contentPage = $contentPageResponse->getContentPage();
        } catch (\Exception) {
            $contentPage = null;
        }

        return $contentPage;
    }
}
