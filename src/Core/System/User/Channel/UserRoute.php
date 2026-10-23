<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Extension\UserRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class UserRoute extends AbstractUserRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractUserRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/user',
        name: 'store-api.account.user',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_ENTITY => UserDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): UserResponse
    {
        return $this->extensions->publish(
            name: UserRouteExtension::NAME,
            extension: new UserRouteExtension($request, $context, $criteria, $user),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): UserResponse => $this->_load($context, $criteria, $user),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria, UserEntity $user): UserResponse
    {
        $criteria->setIds([$user->getId()]);

        $userEntity = $this->userRepository->search($criteria, $context->getContext())->getEntities()->first();
        \assert($userEntity !== null);

        return new UserResponse($userEntity);
    }
}
