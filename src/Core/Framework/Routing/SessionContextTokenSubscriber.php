<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
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
class SessionContextTokenSubscriber implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * Before routing (RouterListener runs at 32); the owner is recognized by a request attribute.
     */
    private const PRIORITY_START = 40;

    /**
     * After every listener that may put the context token onto the response, notably the Core
     * ResponseHeaderListener (0) and the routes setting it themselves.
     */
    private const PRIORITY_STRIP_TOKEN = -1600;

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
            KernelEvents::CONTROLLER => [
                ['resolveFromSession', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
            ],
            KernelEvents::RESPONSE => [
                ['stripContextToken', self::PRIORITY_STRIP_TOKEN],
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

        $this->sessionContextToken->start($mainRequest, $event->getRequest());
    }

    /**
     * Runs after ChannelAuthenticationListener (-2) established the channel and before
     * context resolution (-10).
     */
    public function resolveFromSession(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, ChannelApiRouteScope::class)) {
            return;
        }

        $token = $this->sessionContextToken->read(
            $request,
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID)
        );

        if ($token === null) {
            return;
        }

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
    }

    public function onMemberLogin(MemberLoginEvent $event): void
    {
        $this->rotate($event->getChannelId(), $event->getContextToken());
    }

    public function onMemberLogout(MemberLogoutEvent $event): void
    {
        // the logout route already rotated the context and returns that token in its body
        $this->rotate($event->getChannelId(), $event->getChannelContext()->getToken(), true);
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
    public function stripContextToken(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, ChannelApiRouteScope::class)) {
            return;
        }

        if (!$request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION)) {
            return;
        }

        $event->getResponse()->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);
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

        // an /api request must not migrate the frontend session
        if (!$mainRequest->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)
            && !$this->isRequestScoped($mainRequest, ChannelApiRouteScope::class)
        ) {
            return;
        }

        $this->sessionContextToken->rotate($mainRequest, $channelId, $token, $destroyOldSession);
    }
}
