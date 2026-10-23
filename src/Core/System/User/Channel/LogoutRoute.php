<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserLogoutEvent;
use Contena\Core\System\User\Extension\LogoutRouteExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LogoutRoute extends AbstractLogoutRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfig,
        private readonly CartService $cartService,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractLogoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/logout',
        name: 'store-api.account.logout',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function logout(ChannelContext $context, RequestDataBag $data): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: LogoutRouteExtension::NAME,
            extension: new LogoutRouteExtension($context, $data),
            function: fn (ChannelContext $context, RequestDataBag $data): ContextTokenResponse => $this->_logout($context),
        );
    }

    private function _logout(ChannelContext $context): ContextTokenResponse
    {
        /** @var UserEntity $user */
        $user = $context->getUser();
        if ($this->shouldDelete($context)) {
            $this->cartService->deleteCart($context);
            $this->contextPersister->delete($context->getToken(), $context->getChannelId());
        } else {
            $this->contextPersister->replace($context->getToken(), $context);
        }

        // Update the context for the remainder of the request
        $context = $this->contextService->get(
            new ChannelContextServiceParameters(
                $context->getChannelId(),
                Random::getAlphanumericString(32),
            )
        );

        $event = new UserLogoutEvent($context, $user);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken());
    }

    private function shouldDelete(ChannelContext $context): bool
    {
        $config = $this->systemConfig->get('core.loginRegistration.invalidateSessionOnLogOut', $context->getChannelId());

        if ($config) {
            return true;
        }

        if ($context->getUser() === null) {
            return true;
        }

        return $context->getUser()->getGuest();
    }
}
