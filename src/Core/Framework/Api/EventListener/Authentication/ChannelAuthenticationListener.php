<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Contena\Core\Framework\Routing\RouteScopeCheckTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\Api\EventListener\ChannelAuthenticationListenerTest
 */
class ChannelAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly MaintenanceModeResolver $maintenanceModeResolver
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'validateRequest',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE,
            ],
        ];
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        if (!$this->isRequestScoped($request, ChannelApiRouteScope::class)) {
            return;
        }

        $accessKey = $request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);
        if (!$accessKey) {
            throw ApiException::unauthorized(
                'header',
                \sprintf('Header "%s" is required.', PlatformRequest::HEADER_ACCESS_KEY)
            );
        }

        $origin = AccessKeyHelper::getOrigin($accessKey);
        if ($origin !== 'sales-channel') {
            throw ApiException::channelNotFound();
        }

        $channelData = $this->getChannelData($accessKey);

        $this->handleMaintenanceMode($request, $channelData);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $channelData['id']);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * @return array<string, mixed>
     */
    private function getChannelData(string $accessKey): array
    {
        $builder = $this->connection->createQueryBuilder();

        $channelData = $builder->select(
            'channel.id AS id',
            'channel.maintenance AS maintenance',
            // @deprecated tag:v6.8.0 - remove the COALESCE fallback to the deprecated `maintenance_ip_whitelist` column
            'COALESCE(channel.maintenance_ip_allowlist, channel.maintenance_ip_whitelist) AS maintenanceIpAllowlist'
        )
            ->from('channel')
            ->where('channel.access_key = :accessKey')
            ->andWhere('channel.active = :active')
            ->setParameter('accessKey', $accessKey)
            ->setParameter('active', true, Types::BOOLEAN)
            ->executeQuery()
            ->fetchAssociative();

        if (!\is_array($channelData)) {
            throw ApiException::channelNotFound();
        }

        $id = $channelData['id'] ?? null;

        if ($id === null || $id === '') {
            throw ApiException::channelNotFound();
        }

        $channelData['id'] = Uuid::fromBytesToHex($id);

        return $channelData;
    }

    /**
     * @param array<string, mixed> $channelData
     */
    private function handleMaintenanceMode(Request $request, array $channelData): void
    {
        $maintenance = (bool) ($channelData['maintenance'] ?? false);

        if (!$maintenance) {
            return;
        }

        if ($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE)) {
            return;
        }

        try {
            /** @var list<string> $allowedIps */
            $allowedIps = Json::decodeToList((string) ($channelData['maintenanceIpAllowlist'] ?? ''));
        } catch (UtilException) {
            return;
        }

        if ($this->maintenanceModeResolver->isClientAllowed($request, $allowedIps)) {
            return;
        }

        throw ApiException::channelInMaintenanceMode();
    }
}
