<?php declare(strict_types=1);

namespace Contena\Core\System\User\Api;

use Doctrine\DBAL\Connection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Channel\AbstractConvertGuestRoute;
use Contena\Core\System\User\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConvertGuestController
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly AbstractConvertGuestRoute $convertGuestRoute,
        private readonly AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        path: '/api/_action/user-convert/{userId}',
        name: 'api.action.user.convert',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['user:update']],
        methods: ['POST']
    )]
    public function convert(Request $request, Context $context, string $userId): NoContentResponse
    {
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();

        if (!$user instanceof UserEntity) {
            throw UserException::userNotFoundByIdException($userId);
        }

        $token = $this->connection->fetchOne(
            'SELECT token FROM channel_api_context WHERE user_id = :userId  AND channel_id = :channelId',
            [
                'userId' => Uuid::fromHexToBytes($userId),
                'channelId' => Uuid::fromHexToBytes($user->getChannelId()),
            ]
        ) ?: Random::getAlphanumericString(32);

        $channelContext = $this->contextService->get(
            new ChannelContextServiceParameters(
                $user->getChannelId(),
                $token,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                null,
                $context,
                $userId
            )
        );

        $requestBag = new RequestDataBag([
            'password' => $request->request->getString('password') ?: Random::getAlphanumericString(16),
        ]);
        $this->convertGuestRoute->convertGuest($requestBag, $channelContext, $user);

        if ($request->request->getString('password')) {
            return new NoContentResponse();
        }

        $recoveryData = new RequestDataBag([
            'email' => $user->getEmail(),
            'frontendUrl' => $this->getChannelDomain($channelContext),
        ]);
        $this->sendPasswordRecoveryMailRoute->sendRecoveryMail($recoveryData, $channelContext);

        return new NoContentResponse();
    }

    private function getChannelDomain(ChannelContext $context): string
    {
        $domains = $context->getChannel()->getDomains();

        $filtered = $domains?->filter(
            fn (ChannelDomainEntity $domain) => $domain->getLanguageId() === $context->getUser()?->getLanguageId()
        );

        $domain = $filtered?->first() ?? $domains?->first();

        if (!$domain instanceof ChannelDomainEntity) {
            throw UserException::channelDomainNotFound($context->getChannel()->getId());
        }

        return $domain->getUrl();
    }
}
