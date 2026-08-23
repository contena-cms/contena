<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class MemberLogoutSubscriber implements EventSubscriberInterface
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
            MemberLogoutEvent::class => ['onMemberLogout', -10000],
        ];
    }

    public function onMemberLogout(MemberLogoutEvent $event): void
    {
        $event->getChannelContext()->setImitatingUserId(null);

        $mainRequest = $this->requestStack->getMainRequest();

        // Only clear an initialized frontend session. Channel API requests use their context token directly.
        if (!$mainRequest?->hasSession(true)) {
            return;
        }

        $mainRequest->getSession()->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
    }
}
