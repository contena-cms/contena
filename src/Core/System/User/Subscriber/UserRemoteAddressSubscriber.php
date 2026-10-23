<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
readonly class UserRemoteAddressSubscriber implements EventSubscriberInterface
{
    private const STORE_PLAIN_IP_ADDRESS = 'core.loginRegistration.userIpAddressesNotAnonymously';

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
        private SystemConfigService $configService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserLoginEvent::class => 'updateRemoteAddressByLogin',
        ];
    }

    public function updateRemoteAddressByLogin(UserLoginEvent $event): void
    {
        $request = $this->requestStack
            ->getMainRequest();

        if (!$request) {
            return;
        }

        $clientIp = $request->getClientIp();

        if ($clientIp === null) {
            return;
        }

        if (!$this->configService->getBool(self::STORE_PLAIN_IP_ADDRESS)) {
            $clientIp = IpUtils::anonymize($clientIp);
        }

        $this->connection->update('user', [
            'remote_address' => $clientIp,
        ], [
            'id' => Uuid::fromHexToBytes($event->getUserId()),
        ]);
    }
}
