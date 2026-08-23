<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\Json;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class MaintenanceController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly MaintenancePageLoader $maintenancePageLoader,
        private readonly MaintenanceModeResolver $maintenanceModeResolver,
        private readonly AbstractContentRoute $contentRoute,
    ) {
    }

    #[Route(
        path: '/maintenance',
        name: 'frontend.maintenance.page',
        defaults: [
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function renderMaintenancePage(Request $request, ChannelContext $context): ?Response
    {
        if ($this->maintenanceModeResolver->shouldRedirectToFrontend($request)) {
            if ($request->query->getString('redirectTo') !== '') {
                return $this->createActionResponse($request);
            }

            return $this->redirectToRoute('frontend.home.page');
        }

        $maintenanceLandingPageId = $this->systemConfigService->getString('core.basicInformation.maintenancePage', $context->getChannelId());
        if ($maintenanceLandingPageId === '') {
            $response = $this->renderFrontend('@Frontend/frontend/page/error/error-maintenance.html.twig');
        } else {
            $maintenancePage = $this->maintenancePageLoader->load($maintenanceLandingPageId, $request, $context);
            $contentResponse = $this->contentRoute->load('/landing-page/' . $maintenanceLandingPageId, $request, $context);
            \assert($contentResponse instanceof ContentRouteResponse);

            $response = $this->renderFrontend('@Frontend/frontend/page/error/error-maintenance.html.twig', [
                'page' => $maintenancePage,
                'contentPage' => $contentResponse->getContentPage(),
                'isNewContentStructure' => true,
            ]);
        }

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
        $response->headers->set('Retry-After', '3600');
        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    #[Route(
        path: '/maintenance/singlepage/{id}',
        name: 'frontend.maintenance.singlepage',
        defaults: [
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function renderSinglePage(string $id, Request $request, ChannelContext $context): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        $page = $this->maintenancePageLoader->load($id, $request, $context);
        $contentResponse = $this->contentRoute->load('/landing-page/' . $id, $request, $context);
        \assert($contentResponse instanceof ContentRouteResponse);

        $response = $this->renderFrontend('@Frontend/frontend/page/landing-page/index.html.twig', [
            'page' => $page,
            'contentPage' => $contentResponse->getContentPage(),
            'isNewContentStructure' => true,
        ]);
        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    private function addAllowlistIpHeader(Request $request, Response $response): void
    {
        $ips = $request->attributes->get(ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST);

        if ($ips) {
            $response->headers->set(
                HttpCacheKernel::MAINTENANCE_ALLOWLIST_HEADER,
                implode(',', Json::decodeToList((string) $ips))
            );
        }
    }
}
