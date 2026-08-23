<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Routing\Event\MaintenanceModeRequestEvent;
use Contena\Core\Framework\Util\Json;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class MaintenanceModeResolver
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function shouldBeCached(Request $request): bool
    {
        return !$this->isActive($request) || !$this->isClientAllowed($request, self::getIps($request));
    }

    /**
     * @param array<string> $allowedIps
     */
    public function isClientAllowed(Request $request, array $allowedIps): bool
    {
        $isAllowed = IpUtils::checkIp((string) $request->getClientIp(), $allowedIps);

        $event = new MaintenanceModeRequestEvent($request, $allowedIps, $isAllowed);

        $this->eventDispatcher->dispatch($event);

        return $event->isClientAllowed();
    }

    public function isMaintenanceRequest(Request $request): bool
    {
        return $this->isActive($request) && !$this->isClientAllowed($request, self::getIps($request));
    }

    /**
     * @return string[]
     */
    private static function getIps(Request $request): array
    {
        $allowlist = $request->attributes->get(PlatformRequest::ATTRIBUTE_MAINTENANCE_IP_ALLOWLIST, '');

        /** @var list<string> $allowedIps */
        $allowedIps = Json::decodeToList((string) $allowlist);

        return $allowedIps;
    }

    private function isActive(Request $request): bool
    {
        return (bool) $request->attributes->get(PlatformRequest::ATTRIBUTE_MAINTENANCE);
    }
}
