<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class LogoutRoute extends AbstractLogoutRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChannelContextServiceInterface $contextService,
    ) {
    }

    public function getDecorated(): AbstractLogoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/logout',
        name: 'channel-api.account.logout',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST],
    )]
    public function logout(ChannelContext $context, RequestDataBag $data): ContextTokenResponse
    {
        $member = $context->getMember();
        \assert($member instanceof MemberEntity);

        $this->contextPersister->delete($context->getToken(), $context->getChannelId());

        $context = $this->contextService->get(new ChannelContextServiceParameters(
            channelId: $context->getChannelId(),
            token: Random::getAlphanumericString(32),
            languageId: $context->getLanguageId(),
            domainId: $context->getDomainId(),
            originalContext: $context->getContext(),
        ));

        $this->eventDispatcher->dispatch(new MemberLogoutEvent($context, $member));

        return new ContextTokenResponse($context->getToken());
    }
}
