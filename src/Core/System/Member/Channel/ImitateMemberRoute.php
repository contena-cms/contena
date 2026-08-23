<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\ImitateMemberTokenGenerator;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => false,
    ]
)]
class ImitateMemberRoute extends AbstractImitateMemberRoute
{
    final public const TOKEN = 'token';

    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ImitateMemberTokenGenerator $imitateMemberTokenGenerator,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractChannelContextFactory $channelContextFactory,
    ) {
    }

    public function getDecorated(): AbstractImitateMemberRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/login/imitate-member',
        name: 'channel-api.account.imitate-member-login',
        methods: [Request::METHOD_POST]
    )]
    public function imitateMemberLogin(RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        $tokenString = $data->getString(self::TOKEN);
        $token = $this->imitateMemberTokenGenerator->decode($tokenString);

        if ($token->channelId !== $context->getChannelId()) {
            throw MemberException::invalidImitationToken($tokenString);
        }

        if ($context->getMemberId() === $token->memberId) {
            return new ContextTokenResponse($context->getToken());
        }

        if ($context->getMember()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->channelContextFactory->create($newTokenResponse->getToken(), $context->getChannelId());
        }

        $context->setImitatingUserId($token->iss);

        $newToken = $this->accountService->loginById($token->memberId, $context);

        return new ContextTokenResponse($newToken);
    }
}
