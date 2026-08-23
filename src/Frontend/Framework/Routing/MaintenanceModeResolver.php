<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Contena\Core\Framework\Util\Json;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Controller\ErrorController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MaintenanceModeResolver
{
    /**
     * @internal
     */
    public function __construct(
        protected RequestStack $requestStack,
        protected CoreMaintenanceModeResolver $maintenanceModeResolver,
    ) {
    }

    /**
     * shouldRedirect returns true, when the given request should be redirected to the maintenance page.
     * This would be the case, for example, when the maintenance mode is active and the client's IP address
     * is not listed in the maintenance mode whitelist.
     */
    public function shouldRedirect(Request $request): bool
    {
        return $this->isChannelRequest()
            && !$request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE)
            && !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && $this->isMaintenanceRequest($request);
    }

    /**
     * shouldRedirectToFrontend returns true, when the given request to the maintenance page should be redirected to the frontend.
     * This would be the case, for example, when the maintenance mode is not active or if it is active
     * the client's IP address is listed in the maintenance mode whitelist.
     */
    public function shouldRedirectToFrontend(Request $request): bool
    {
        return !$this->isXmlHttpRequest($request)
            && !$this->isErrorControllerRequest($request)
            && !$this->isMaintenanceRequest($request);
    }

    public function shouldBeCached(Request $request): bool
    {
        return !$this->isMaintenanceModeActive() || !$this->isClientAllowed($request);
    }

    /**
     * isMaintenanceRequest returns true, when the maintenance mode is active and the client's IP address
     * is not listed in the maintenance mode whitelist.
     */
    public function isMaintenanceRequest(Request $request): bool
    {
        return $this->isMaintenanceModeActive() && !$this->isClientAllowed($request);
    }

    private function isChannelRequest(): bool
    {
        $main = $this->requestStack->getMainRequest();

        return (bool) $main?->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST);
    }

    private function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest();
    }

    private function isErrorControllerRequest(Request $request): bool
    {
        return $request->attributes->get('_route') === null
            && $request->attributes->get('_controller') === ErrorController::class . '::error';
    }

    private function isMaintenanceModeActive(): bool
    {
        $main = $this->requestStack->getMainRequest();

        return (bool) $main?->attributes->get(ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE);
    }

    private function isClientAllowed(Request $request): bool
    {
        $main = $this->requestStack->getMainRequest();
        $allowlist = $main?->attributes->get(ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST) ?? '';

        /** @var list<string> $allowedIps */
        $allowedIps = Json::decodeToList((string) $allowlist);

        return $this->maintenanceModeResolver->isClientAllowed($request, $allowedIps);
    }
}
