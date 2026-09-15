<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener\Authentication;

use Contena\Core\Framework\Api\OAuth\GrantTypeFactory;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RouteScopeCheckTrait;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\PlatformRequest;
use League\OAuth2\Server\AuthorizationServer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class ApiAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly SymfonyBearerTokenValidator $symfonyBearerTokenValidator,
        private readonly AuthorizationServer $authorizationServer,
        private readonly GrantTypeFactory $grantTypeFactory,
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly string $accessTokenTtl = 'PT10M',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setupOAuth', 128],
            ],
            KernelEvents::CONTROLLER => [
                ['validateRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE],
            ],
        ];
    }

    public function setupOAuth(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $accessTokenInterval = new \DateInterval($this->accessTokenTtl);
        foreach ($this->grantTypeFactory->createGrantTypes() as $grantType) {
            $this->authorizationServer->enableGrantType($grantType, $accessTokenInterval);
        }
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, false)) { // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        if (!$this->isRequestScoped($request, ApiContextRouteScopeDependant::class)) {
            return;
        }

        $this->symfonyBearerTokenValidator->validateAuthorization($event->getRequest());
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}
