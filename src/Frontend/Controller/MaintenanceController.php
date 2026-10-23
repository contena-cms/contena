<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\Json;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoadedHook;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
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
        private readonly MaintenanceModeResolver $maintenanceModeResolver
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
        if ($this->maintenanceModeResolver->shouldRedirectToShop($request)) {
            if ($request->query->getString('redirectTo') !== '') {
                return $this->createActionResponse($request);
            }

            return $this->redirectToRoute('frontend.home.page');
        }

        $channelId = $context->getChannelId();
        $maintenanceLayoutId = $this->systemConfigService->getString('core.basicInformation.maintenancePage', $channelId);

        if ($maintenanceLayoutId === '') {
            $response = $this->renderFrontend(
                '@Frontend/frontend/page/error/error-maintenance.html.twig'
            );

            $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
            $response->headers->set('Retry-After', '3600');

            $this->addAllowlistIpHeader($request, $response);

            return $response;
        }

        $maintenancePage = $this->maintenancePageLoader->load($maintenanceLayoutId, $request, $context);

        $this->hook(new MaintenancePageLoadedHook($maintenancePage, $context));

        $response = $this->renderFrontend(
            '@Frontend/frontend/page/error/error-maintenance.html.twig',
            ['page' => $maintenancePage]
        );

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
        $response->headers->set('Retry-After', '3600');

        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    /**
     * Route for standalone cms pages during maintenance
     */
    #[Route(
        path: '/maintenance/singlepage/{id}',
        name: 'frontend.maintenance.singlepage',
        defaults: [
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function renderSinglePage(string $id, Request $request, ChannelContext $channelContext): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        $cmsPage = $this->maintenancePageLoader->load($id, $request, $channelContext);

        $this->hook(new MaintenancePageLoadedHook($cmsPage, $channelContext));

        $response = $this->renderFrontend(
            '@Frontend/frontend/page/content/single-cms-page.html.twig',
            ['page' => $cmsPage]
        );

        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    private function addAllowlistIpHeader(Request $request, Response $response): void
    {
        $ips = $request->attributes->get(ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST)
            // @deprecated tag:v6.8.0 - remove the fallback to the deprecated attribute
            ?? $request->attributes->get(ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_WHITLELIST);

        if ($ips) {
            $ips = implode(',', Json::decodeToList((string) $ips));

            $response->headers->set(HttpCacheKernel::MAINTENANCE_ALLOWLIST_HEADER, $ips);
            // @deprecated tag:v6.8.0 - remove setting the deprecated header
            $response->headers->set(HttpCacheKernel::MAINTENANCE_WHITELIST_HEADER, $ips);
        }
    }
}
