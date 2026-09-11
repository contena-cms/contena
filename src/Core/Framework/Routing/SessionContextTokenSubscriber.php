<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs the session held context token through the request lifecycle for the frontend (owner) and
 * Channel API borrowers alike, see SessionContextTokenAccessor. Rotations are followed through the
 * events that cause them.
 *
 * @internal
 */
#[Package('framework')]
class SessionContextTokenSubscriber implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * Before routing (RouterListener runs at 32); the owner is recognized by a request attribute.
     */
    private const PRIORITY_START = 40;

    /**
     * After CacheResponseSubscriber::setResponseCache (-1500), which rewrites Cache-Control wholesale,
     * and after ResponseHeaderListener (0) has echoed the request's context token onto the response.
     */
    private const PRIORITY_CACHE_CONTROL = -1600;

    /**
     * @internal
     */
    public function __construct(
        private readonly SessionContextTokenAccessor $sessionContextToken,
        private readonly RequestStack $requestStack,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', self::PRIORITY_START],
            ],
            KernelEvents::RESPONSE => [
                ['protectSessionResolvedResponse', self::PRIORITY_CACHE_CONTROL],
            ],
            MemberLoginEvent::class => 'onMemberLogin',
            MemberLogoutEvent::class => 'onMemberLogout',
            ChannelContextResolvedEvent::class => 'onContextResolved',
        ];
    }

    public function startSession(RequestEvent $event): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        $this->sessionContextToken->startForOwner($mainRequest, $event->getRequest());
    }

    public function onMemberLogin(MemberLoginEvent $event): void
    {
        $this->rotate($event->getChannelId(), $event->getContextToken());
    }

    public function onMemberLogout(MemberLogoutEvent $event): void
    {
        $this->rotate($event->getChannelId(), Random::getAlphanumericString(32), true);
    }

    public function onContextResolved(ChannelContextResolvedEvent $event): void
    {
        $context = $event->getChannelContext();

        if ($event->getUsedToken() === $context->getToken()) {
            return;
        }

        $this->rotate($context->getChannelId(), $context->getToken());
    }

    /**
     * Session-sourced clients do not need a response token header. Existing response-body token
     * fields remain unchanged, so this does not make the token inaccessible to same-origin scripts.
     */
    public function protectSessionResolvedResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, ChannelApiRouteScope::class)) {
            return;
        }

        if (!$request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $this->denySharedCache($response);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function rotate(string $channelId, string $token, bool $destroyOldSession = false): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        $this->sessionContextToken->rotate($mainRequest, $channelId, $token, $destroyOldSession);
    }

    /**
     * The no-store policy of CacheResponseSubscriber carries no `private` directive.
     */
    private function denySharedCache(Response $response): void
    {
        $response->headers->remove('cache-control');

        $response->setCache([
            'private' => true,
            'no_store' => true,
            'no_cache' => true,
            'must_revalidate' => true,
            'max_age' => 0,
        ]);
    }
}
