<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Routing\Exception\MemberNotLoggedInRoutingException;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Event\MaintenanceRedirectEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class FrontendSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly MaintenanceModeResolver $maintenanceModeResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['memberNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            MemberLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            MemberLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            ChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];
    }

    public function startSession(): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }
        if (!$mainRequest->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)) {
            return;
        }
        /** @phpstan-ignore contena.unsafeRequestHasSession (using $skipIfUninitialized = false as session will be started intentionally later; this can take the PHP session lock and is limited to frontend routing starting the frontend session when needed.) */
        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!$session->isStarted()) {
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        $channelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
        if ($channelId === null) {
            $channelContext = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
            if ($channelContext instanceof ChannelContext) {
                $channelId = $channelContext->getChannelId();
            }
        }

        // When member binding is enabled, store tokens per channel to prevent
        // bound members from being logged out when visiting other channels
        $bindingEnabled = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isMemberBoundToChannel');
        $tokenKey = $bindingEnabled
            ? PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId
            : PlatformRequest::HEADER_CONTEXT_TOKEN;

        if ($this->shouldRenewToken($session, $channelId, $tokenKey)) {
            $token = Random::getAlphanumericString(32);
            $session->set($tokenKey, $token);
            $session->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $channelId);
        }

        $contextToken = $session->get($tokenKey);

        // Always keep the default key in sync with the current token for backward compatibility
        // This ensures code that relies on the default key (e.g., anonymous users) still works
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);

        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);

        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest && $mainRequest !== $currentRequest) {
            $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        }
    }

    public function updateSessionAfterLogin(MemberLoginEvent $event): void
    {
        $token = $event->getContextToken();

        $this->updateSession($token);
    }

    public function updateSessionAfterLogout(): void
    {
        $newToken = Random::getAlphanumericString(32);

        $this->updateSession($newToken, true);
    }

    public function updateSession(string $token, bool $destroyOldSession = false): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }
        if (!$mainRequest->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)) {
            return;
        }
        if (!\in_array(FrontendRouteScope::ID, $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return;
        }

        // Frontend sessions are started during kernel.request, before member login and logout events are dispatched.
        if (!$mainRequest->hasSession(true)) {
            return;
        }

        $session = $mainRequest->getSession();
        $session->migrate($destroyOldSession);
        $session->set('sessionId', $session->getId());

        // When member binding is enabled, store tokens per channel
        $bindingEnabled = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isMemberBoundToChannel');
        if ($bindingEnabled) {
            $channelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
            if ($channelId) {
                $tokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
                $session->set($tokenKey, $token);
            }
        }

        // Always set the default key for backward compatibility
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function memberNotLoggedInHandler(ExceptionEvent $event): void
    {
        if (!$event->getRequest()->attributes->has(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if (!$this->shouldRedirectLoginPage($exception, $request)) {
            return;
        }

        $parameters = [
            'redirectTo' => $request->attributes->get('_route'),
            'redirectParameters' => json_encode($request->attributes->get('_route_params'), \JSON_THROW_ON_ERROR),
        ];

        $redirectResponse = new RedirectResponse($this->router->generate('frontend.account.login.page', $parameters));

        $event->setResponse($redirectResponse);
    }

    public function maintenanceResolver(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->maintenanceModeResolver->shouldRedirect($request)) {
            $parameters = [];
            $route = $request->attributes->get('_route');
            if ($route !== null) {
                $parameters['redirectTo'] = $route;
                $requestParameters = $this->getRequestParameters($request);

                if ($requestParameters !== []) {
                    $parameters['redirectParameters'] = json_encode($requestParameters, \JSON_THROW_ON_ERROR);
                }
            }

            $redirectEvent = new MaintenanceRedirectEvent('frontend.maintenance.page', $parameters, Response::HTTP_TEMPORARY_REDIRECT);
            $this->eventDispatcher->dispatch($redirectEvent);

            $event->setResponse(
                new RedirectResponse($this->router->generate($redirectEvent->getRoute(), $redirectEvent->getParameters()), $redirectEvent->getStatus())
            );
        }
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $scope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(FrontendRouteScope::ID, $scope, true)) {
            return;
        }

        $isAllowed = $request->attributes->getBoolean('XmlHttpRequest');
        if ($isAllowed) {
            return;
        }

        $route = $request->attributes->get('_route');
        $url = $request->getUri();
        $referer = $request->headers->get('referer');

        throw RoutingException::accessDeniedForXmlHttpRequest($route, $url, $referer);
    }

    // used to switch session token - when the context token expired
    public function replaceContextToken(ChannelContextResolvedEvent $event): void
    {
        $context = $event->getChannelContext();

        // only update session if token expired and switched
        if ($event->getUsedToken() === $context->getToken()) {
            return;
        }

        $this->updateSession($context->getToken());
    }

    private function shouldRenewToken(SessionInterface $session, ?string $channelId = null, ?string $tokenKey = null): bool
    {
        $keyToCheck = $tokenKey ?? PlatformRequest::HEADER_CONTEXT_TOKEN;

        if (!$session->has($keyToCheck) || $channelId === null) {
            return true;
        }

        // When using per-channel tokens (binding enabled), we don't renew based on channel change
        // because each channel has its own token. We only renew if token doesn't exist for this key.
        if ($this->systemConfigService->getBool('core.systemWideLoginRegistration.isMemberBoundToChannel')) {
            // If we're checking a channel-specific key, token existence was already checked above
            if ($tokenKey !== null && $tokenKey !== PlatformRequest::HEADER_CONTEXT_TOKEN) {
                $expectedTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;

                // Don't renew if the token key matches the current channel (token already exists for this channel)
                return $tokenKey !== $expectedTokenKey;
            }

            // For backward compatibility with default key, check if channel changed
            return $session->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID) !== $channelId;
        }

        return false;
    }

    private function shouldRedirectLoginPage(\Throwable $ex, Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return false;
        }

        if ($ex instanceof MemberNotLoggedInRoutingException) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getRequestParameters(Request $request): array
    {
        $requestParameters = $request->query->all();
        $routeParams = $request->attributes->get('_route_params');

        if (\is_array($routeParams)) {
            foreach ($routeParams as $key => $value) {
                // we don't want any default route parameter, e.g. _httpCache or _store
                if (\in_array($key, PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS, true)) {
                    continue;
                }

                $requestParameters[$key] = $value;
            }
        }

        return $requestParameters;
    }
}
