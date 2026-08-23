<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Sitemap\Channel\SitemapFileRoute;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Sitemap\SitemapPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class SitemapController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapPageLoader $sitemapPageLoader,
        private readonly SitemapFileRoute $sitemapFileRoute
    ) {
    }

    #[Route(path: '/sitemap.xml', name: 'frontend.sitemap.xml', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function sitemapXml(ChannelContext $context, Request $request): Response
    {
        $page = $this->sitemapPageLoader->load($request, $context);

        $response = $this->renderFrontend('@Frontend/frontend/page/sitemap/sitemap.xml.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/xml; charset=utf-8');

        return $response;
    }

    #[Route(path: '/sitemap/{filePath}', name: 'frontend.sitemap.proxy', requirements: ['filePath' => '.+\\.xml\\.gz'], methods: ['GET'])]
    public function sitemapProxy(ChannelContext $context, Request $request, string $filePath): Response
    {
        return $this->sitemapFileRoute->getSitemapFile($request, $context, $filePath);
    }
}
