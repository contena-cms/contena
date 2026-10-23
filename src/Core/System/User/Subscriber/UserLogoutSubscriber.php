<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Event\UserLogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class UserLogoutSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserLogoutEvent::class => ['onUserLogout', -10000],
        ];
    }

    public function onUserLogout(UserLogoutEvent $event): void
    {
        $event->getChannelContext()->setImitatingUserId(null);

        $mainRequest = $this->requestStack->getMainRequest();

        // Only clear an initialized frontend session. Store API requests use their context token directly.
        if (!$mainRequest?->hasSession(true)) {
            return;
        }

        $mainRequest->getSession()->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
    }
}
