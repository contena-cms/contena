<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\Exception\MemberNotLoggedInRoutingException;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Event\MaintenanceRedirectEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Session and context token handling lives in \Contena\Core\Framework\Routing\SessionContextTokenSubscriber.
 *
 * @internal
 */
class FrontendSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly MaintenanceModeResolver $maintenanceModeResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['memberNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
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

    private function shouldRedirectLoginPage(\Throwable $ex, Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return false;
        }

        return $ex instanceof MemberNotLoggedInRoutingException;
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
